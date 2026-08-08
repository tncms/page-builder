// Phase 26.15 — runtime UX: recently created/used, keyboard shortcut, quick actions,
// and the a11y Escape-to-close. All runtime-only (no persistence).

import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'

import { usePatternRuntimeStore, registerPatternShortcuts, RECENT_LIMIT } from '../PatternRuntimeStore'
import PatternQuickActions from '../PatternQuickActions.vue'
import { usePatternPickerStore } from '../../picker/patternPickerStore'
import { usePatternManagementStore } from '../../management/patternManagementStore'
import { usePatternAuthoringStore } from '../../authoring/patternAuthoringStore'
import PatternRenameDialog from '../../management/PatternRenameDialog.vue'
import { patternProvider } from '../../provider'
import { patternLibraryRegistry, createPatternDefinition, type PatternDefinition } from '../../library'
import { useEditorStore } from '../../../stores/editor'
import type { SectionNode, LayoutPayload } from '../../../types'

function section(): SectionNode {
  return { type: 'section', data: { background: '#fff' }, blocks: [{ type: 'heading', data: {} }] }
}
function register(name: string): PatternDefinition {
  return patternProvider.register(createPatternDefinition({ name, category: 'hero', sections: [section()] }))
}
function keyEvent(over: Partial<KeyboardEventInit> = {}): KeyboardEvent {
  return new KeyboardEvent('keydown', { ctrlKey: true, shiftKey: true, key: 'P', ...over })
}

beforeEach(() => {
  setActivePinia(createPinia())
  patternLibraryRegistry.clear()
})

// ---- Recently created / used -------------------------------------------------

describe('recently created / used', () => {
  it('tracks most-recent-first, de-duplicated', () => {
    const a = register('A')
    const b = register('B')
    const runtime = usePatternRuntimeStore()
    runtime.trackCreated(a.id)
    runtime.trackCreated(b.id)
    runtime.trackCreated(a.id) // re-track moves A to front, no dupe
    expect(runtime.recentlyCreated.map((p) => p.id)).toEqual([a.id, b.id])
  })

  it('resolves live definitions and drops deleted ones (on recompute)', () => {
    const a = register('A')
    const b = register('B')
    const runtime = usePatternRuntimeStore()
    runtime.trackUsed(a.id)
    expect(runtime.recentlyUsed.map((p) => p.name)).toEqual(['A'])

    // The getter resolves through the provider, so a later recompute reflects a rename…
    patternProvider.update(a.id, { name: 'A renamed' })
    runtime.trackUsed(a.id) // state change → getter recomputes
    expect(runtime.recentlyUsed[0].name).toBe('A renamed')

    // …and drops a deleted pattern.
    patternProvider.remove(a.id)
    runtime.trackUsed(b.id) // state change → getter recomputes
    expect(runtime.recentlyUsed.map((p) => p.id)).toEqual([b.id])
  })

  it('caps each list at RECENT_LIMIT', () => {
    const runtime = usePatternRuntimeStore()
    for (let i = 0; i < RECENT_LIMIT + 5; i++) runtime.trackCreated(`p${i}`)
    expect(runtime.recentlyCreatedIds).toHaveLength(RECENT_LIMIT)
  })
})

// ---- Keyboard shortcut -------------------------------------------------------

describe('keyboard shortcut', () => {
  it('opens the existing picker on Ctrl+Shift+P', () => {
    const runtime = usePatternRuntimeStore()
    const picker = usePatternPickerStore()
    expect(runtime.handleShortcut(keyEvent())).toBe(true)
    expect(picker.open).toBe(true)
  })

  it('also matches Cmd+Shift+P and ignores other combos', () => {
    const runtime = usePatternRuntimeStore()
    expect(runtime.handleShortcut(keyEvent({ ctrlKey: false, metaKey: true }))).toBe(true)
    expect(runtime.handleShortcut(keyEvent({ shiftKey: false }))).toBe(false)
    expect(runtime.handleShortcut(keyEvent({ key: 'K' }))).toBe(false)
  })

  it('registerPatternShortcuts attaches + disposes a window listener', () => {
    const target = document.createElement('div')
    const dispose = registerPatternShortcuts(target)
    target.dispatchEvent(keyEvent())
    expect(usePatternPickerStore().open).toBe(true)

    usePatternPickerStore().closePicker()
    dispose()
    target.dispatchEvent(keyEvent())
    expect(usePatternPickerStore().open).toBe(false) // listener removed
  })
})

// ---- Flow integration: created / used are tracked ----------------------------

describe('tracking through the real flows', () => {
  function seedEditor() {
    const editor = useEditorStore()
    editor.currentPayload = { schema: 'pagebuilder/01', sections: [{ id: 'sec-1', type: 'section', data: {}, blocks: [] }] } as LayoutPayload
    editor.selectComponent('sec-1')
    let n = 0
    const runCommand = vi.fn(async (b: LayoutPayload, command: string, params: Record<string, unknown>) => {
      if (command !== 'insert-pattern') return { payload: b, selection: { selected: null } }
      const added = (params.sections as SectionNode[]).map((s) => ({ ...s, id: `srv-${(n += 1)}` }))
      return { payload: { ...b, sections: [...b.sections, ...added] }, selection: { selected: added[0].id } }
    })
    ;(editor as unknown as { client: unknown }).client = { runCommand }
    return editor
  }

  it('authoring save records recently created', () => {
    seedEditor()
    const authoring = usePatternAuthoringStore()
    authoring.openDialog(); authoring.setName('Saved'); authoring.setCategory('hero'); authoring.save()
    const runtime = usePatternRuntimeStore()
    expect(runtime.recentlyCreated.map((p) => p.name)).toEqual(['Saved'])
  })

  it('picker insert records recently used', async () => {
    const editor = seedEditor()
    const a = register('A')
    await usePatternPickerStore().insert(a.id)
    expect(usePatternRuntimeStore().recentlyUsed.map((p) => p.id)).toEqual([a.id])
    expect(editor.currentPayload.sections).toHaveLength(2) // inserted (unchanged pipeline)
  })

  it('management duplicate records recently created', () => {
    const a = register('A')
    const mgmt = usePatternManagementStore()
    mgmt.load()
    const copy = mgmt.duplicate(a.id)!
    expect(usePatternRuntimeStore().recentlyCreated.map((p) => p.id)).toEqual([copy.id])
  })
})

// ---- Quick actions component -------------------------------------------------

describe('PatternQuickActions', () => {
  it('renders the four actions and routes them to existing flows', async () => {
    const a = register('A')
    const picker = usePatternPickerStore()
    const mgmt = usePatternManagementStore()
    const insert = vi.spyOn(picker, 'insert').mockResolvedValue(true)
    const dup = vi.spyOn(mgmt, 'duplicate').mockReturnValue(null)

    const wrapper = mount(PatternQuickActions, { props: { pattern: a } })
    expect(wrapper.findAll('.pb-quick__btn')).toHaveLength(4)

    await wrapper.find('[data-action="insert"]').trigger('click')
    expect(insert).toHaveBeenCalledWith(a.id)
    await wrapper.find('[data-action="rename"]').trigger('click')
    expect(mgmt.renamingId).toBe(a.id)
    await wrapper.find('[data-action="duplicate"]').trigger('click')
    expect(dup).toHaveBeenCalledWith(a.id)
    await wrapper.find('[data-action="delete"]').trigger('click')
    expect(mgmt.deletingId).toBe(a.id)

    // Accessible labels present.
    expect(wrapper.find('[data-action="insert"]').attributes('aria-label')).toBe('Insert A')
  })
})

// ---- Accessibility: Escape closes a dialog -----------------------------------

describe('dialog accessibility', () => {
  it('Escape closes the rename dialog', async () => {
    const a = register('A')
    const mgmt = usePatternManagementStore()
    mgmt.load()
    mgmt.openRename(a.id)
    const wrapper = mount(PatternRenameDialog)
    await nextTick()
    expect(wrapper.find('[role="dialog"]').attributes('aria-modal')).toBe('true')
    await wrapper.find('.pb-pattern-dialog__input').trigger('keydown', { key: 'Escape' })
    expect(mgmt.renamingId).toBeNull()
  })
})
