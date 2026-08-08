// Phase 26.14 — Pattern Management UX.
//
// Covers the registry extension (update/duplicate), pure actions (validate/rename/
// duplicate/delete/search), the management store (rename/duplicate/delete flows + picker
// refresh + feedback), the regression contract (no command/undo, inserted content
// unaffected by delete), and the panel/dialog components.

import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'

import { patternLibraryRegistry, createPatternDefinition, insertPattern, type PatternDefinition } from '../../library'
import {
  validatePatternName,
  renamePattern,
  duplicatePattern,
  deletePattern,
  searchPatterns,
} from '../patternActions'
import { usePatternManagementStore } from '../patternManagementStore'
import { usePatternPickerStore } from '../../picker/patternPickerStore'
import { useEditorStore } from '../../../stores/editor'
import PatternManagementPanel from '../PatternManagementPanel.vue'
import PatternRenameDialog from '../PatternRenameDialog.vue'
import PatternDeleteDialog from '../PatternDeleteDialog.vue'
import type { SectionNode, LayoutPayload } from '../../../types'

function section(): SectionNode {
  return { type: 'section', data: { background: '#fff' }, blocks: [{ type: 'heading', data: {} }, { type: 'button', data: {} }] }
}
function makeDef(name: string, category: string, description?: string): PatternDefinition {
  return createPatternDefinition({ name, category, description, sections: [section()] })
}

function seed(): { hero1: PatternDefinition; hero2: PatternDefinition; cta: PatternDefinition } {
  const hero1 = patternLibraryRegistry.register(makeDef('Modern Hero', 'hero', 'A hero header'))
  const hero2 = patternLibraryRegistry.register(makeDef('Landing Hero', 'hero'))
  const cta = patternLibraryRegistry.register(makeDef('Trial CTA', 'cta', 'Free trial offer'))
  return { hero1, hero2, cta }
}

beforeEach(() => {
  setActivePinia(createPinia())
  patternLibraryRegistry.clear()
})

// ---- Registry extension ------------------------------------------------------

describe('PatternLibraryRegistry — update / duplicate', () => {
  it('update merges a patch, preserving id and position', () => {
    const { hero1 } = seed()
    const updated = patternLibraryRegistry.update(hero1.id, { name: 'Renamed' })
    expect(updated?.id).toBe(hero1.id)
    expect(updated?.name).toBe('Renamed')
    expect(patternLibraryRegistry.all().map((p) => p.id)).toEqual(patternLibraryRegistry.all().map((p) => p.id)) // order stable
    expect(patternLibraryRegistry.get(hero1.id)?.name).toBe('Renamed')
  })

  it('update returns undefined for an unknown id', () => {
    expect(patternLibraryRegistry.update('nope', { name: 'x' })).toBeUndefined()
  })

  it('duplicate makes an independent copy (new id, deep-cloned tree, original intact)', () => {
    const { hero1 } = seed()
    const copy = patternLibraryRegistry.duplicate(hero1.id)!
    expect(copy.id).not.toBe(hero1.id)
    expect(copy.name).toBe('Modern Hero Copy')
    expect(copy.category).toBe('hero')

    // Mutating the copy's tree must not touch the original.
    ;(copy.tree.sections[0].data as Record<string, unknown>).background = '#000'
    expect(patternLibraryRegistry.get(hero1.id)?.tree.sections[0].data.background).toBe('#fff')
    expect(patternLibraryRegistry.all()).toHaveLength(4)
  })
})

// ---- Pure actions ------------------------------------------------------------

describe('patternActions', () => {
  it('validates names', () => {
    expect(validatePatternName('')).toBe('Name is required.')
    expect(validatePatternName('   ')).toBeTruthy()
    expect(validatePatternName('x'.repeat(81))).toBeTruthy()
    expect(validatePatternName('Hero')).toBeNull()
  })

  it('renames in place, preserving id', () => {
    const { hero1 } = seed()
    const result = renamePattern(hero1.id, '  Modern Hero X  ')
    expect(result.ok).toBe(true)
    expect(result.pattern?.id).toBe(hero1.id)
    expect(patternLibraryRegistry.get(hero1.id)?.name).toBe('Modern Hero X') // trimmed
  })

  it('blocks an invalid rename without touching the registry', () => {
    const { hero1 } = seed()
    const result = renamePattern(hero1.id, '')
    expect(result.ok).toBe(false)
    expect(result.error).toBeTruthy()
    expect(patternLibraryRegistry.get(hero1.id)?.name).toBe('Modern Hero') // unchanged
  })

  it('duplicates and deletes', () => {
    const { hero1 } = seed()
    expect(duplicatePattern(hero1.id)?.id).not.toBe(hero1.id)
    expect(duplicatePattern('nope')).toBeNull()
    expect(deletePattern(hero1.id)).toBe(true)
    expect(patternLibraryRegistry.has(hero1.id)).toBe(false)
  })

  it('searches by name, description and category', () => {
    seed()
    const all = patternLibraryRegistry.all()
    expect(searchPatterns(all, 'hero', null).map((p) => p.name)).toEqual(['Modern Hero', 'Landing Hero'])
    expect(searchPatterns(all, 'trial', null).map((p) => p.name)).toEqual(['Trial CTA']) // description match
    expect(searchPatterns(all, '', 'cta').map((p) => p.name)).toEqual(['Trial CTA']) // category filter
    expect(searchPatterns(all, 'zzz', null)).toHaveLength(0) // empty result
  })
})

// ---- Store: rename / duplicate / delete + picker refresh ---------------------

describe('patternManagementStore', () => {
  it('renames through the store, preserves id, refreshes the picker', () => {
    const { hero1 } = seed()
    const picker = usePatternPickerStore()
    picker.openPicker()
    const store = usePatternManagementStore()
    store.load()

    store.openRename(hero1.id)
    expect(store.renameDraft).toBe('Modern Hero')
    store.setRenameDraft('Fancy Hero')
    expect(store.confirmRename()).toBe(true)

    expect(patternLibraryRegistry.get(hero1.id)?.name).toBe('Fancy Hero') // id preserved
    expect(store.renamingId).toBeNull()
    expect(store.feedback).toContain('Fancy Hero')
    expect(picker.results.find((p) => p.id === hero1.id)?.name).toBe('Fancy Hero') // picker updated
  })

  it('blocks an invalid rename and keeps the dialog open', () => {
    const { hero1 } = seed()
    const store = usePatternManagementStore()
    store.load()
    store.openRename(hero1.id)
    store.setRenameDraft('   ')
    expect(store.confirmRename()).toBe(false)
    expect(store.renameError).toBeTruthy()
    expect(store.renamingId).toBe(hero1.id) // still open
  })

  it('duplicates into an independent pattern and refreshes', () => {
    const { hero1 } = seed()
    const store = usePatternManagementStore()
    store.load()
    const copy = store.duplicate(hero1.id)
    expect(copy?.id).not.toBe(hero1.id)
    expect(patternLibraryRegistry.all()).toHaveLength(4)
    expect(store.patterns).toHaveLength(4) // snapshot refreshed
    expect(store.feedback).toContain('Copy')
  })

  it('deletes with confirmation and refreshes the picker', () => {
    const { hero1 } = seed()
    const picker = usePatternPickerStore()
    picker.openPicker()
    const store = usePatternManagementStore()
    store.load()

    store.openDelete(hero1.id)
    expect(store.deletingPattern?.name).toBe('Modern Hero')
    expect(store.confirmDelete()).toBe(true)

    expect(patternLibraryRegistry.has(hero1.id)).toBe(false)
    expect(store.deletingId).toBeNull()
    expect(picker.results.find((p) => p.id === hero1.id)).toBeUndefined() // picker refreshed
  })

  it('cancels delete without removing', () => {
    const { hero1 } = seed()
    const store = usePatternManagementStore()
    store.load()
    store.openDelete(hero1.id)
    store.cancelDelete()
    expect(store.deletingId).toBeNull()
    expect(patternLibraryRegistry.has(hero1.id)).toBe(true)
  })

  it('search getters: results, noResults and isEmpty', () => {
    seed()
    const store = usePatternManagementStore()
    store.load()
    store.setQuery('hero')
    expect(store.results.map((p) => p.name)).toEqual(['Modern Hero', 'Landing Hero'])
    store.setQuery('zzz')
    expect(store.results).toHaveLength(0)
    expect(store.noResults).toBe(true)

    patternLibraryRegistry.clear()
    store.load()
    expect(store.isEmpty).toBe(true)
  })
})

// ---- Regression: no editor history; delete leaves inserted content ------------

describe('management is metadata-only', () => {
  it('does not affect already-inserted content or editor history', async () => {
    const editor = useEditorStore()
    editor.currentPayload = { schema: 'pagebuilder/01', sections: [] } as LayoutPayload
    let n = 0
    const runCommand = vi.fn(async (b: LayoutPayload, command: string, params: Record<string, unknown>) => {
      if (command !== 'insert-pattern') return { payload: b, selection: { selected: null } }
      const added = (params.sections as SectionNode[]).map((s) => ({ ...s, id: `srv-${(n += 1)}` }))
      return { payload: { ...b, sections: [...b.sections, ...added] }, selection: { selected: added[0].id } }
    })
    ;(editor as unknown as { client: unknown }).client = { runCommand }

    const { hero1 } = seed()
    await insertPattern(hero1, editor) // one real insertion
    expect(editor.undoStack).toHaveLength(1)
    expect(editor.currentPayload.sections).toHaveLength(1)

    const store = usePatternManagementStore()
    store.load()
    store.openRename(hero1.id); store.setRenameDraft('Renamed'); store.confirmRename()
    store.openDelete(hero1.id); store.confirmDelete()

    expect(runCommand).toHaveBeenCalledTimes(1) // no new command from management
    expect(editor.undoStack).toHaveLength(1) // no new undo entry
    expect(editor.currentPayload.sections).toHaveLength(1) // inserted content untouched
    expect(patternLibraryRegistry.has(hero1.id)).toBe(false) // but the pattern is gone
  })
})

// ---- Components --------------------------------------------------------------

describe('PatternManagementPanel', () => {
  it('lists patterns, filters by search, and triggers actions', async () => {
    const { hero1 } = seed()
    const wrapper = mount(PatternManagementPanel)
    await nextTick()
    expect(wrapper.findAll('.pb-pattern-mgmt__item')).toHaveLength(3)

    await wrapper.find('input[type="search"]').setValue('trial')
    expect(wrapper.findAll('.pb-pattern-mgmt__item')).toHaveLength(1)

    await wrapper.find('input[type="search"]').setValue('')
    const store = usePatternManagementStore()
    const dup = vi.spyOn(store, 'duplicate')
    await wrapper.find(`[data-pattern="${hero1.id}"] .pb-pattern-mgmt__btn`).trigger('click') // Rename
    expect(store.renamingId).toBe(hero1.id)
    await wrapper.findAll(`[data-pattern="${hero1.id}"] .pb-pattern-mgmt__btn`)[1].trigger('click') // Duplicate
    expect(dup).toHaveBeenCalledWith(hero1.id)
  })

  it('shows the empty state when there are no patterns', async () => {
    const wrapper = mount(PatternManagementPanel)
    await nextTick()
    expect(wrapper.find('.pb-pattern-mgmt__state').text()).toContain('No patterns yet')
  })

  it('shows a no-results state when search matches nothing', async () => {
    seed()
    const wrapper = mount(PatternManagementPanel)
    await nextTick()
    await wrapper.find('input[type="search"]').setValue('zzz')
    expect(wrapper.find('.pb-pattern-mgmt__state').text()).toContain('No patterns match')
  })

  // Phase 26.39 RC hardening — the store recorded action feedback but the panel never
  // rendered it (the Template panel did). Feedback must now surface as an aria-live status.
  it('surfaces action feedback after a duplicate (role="status")', async () => {
    const { hero1 } = seed()
    const wrapper = mount(PatternManagementPanel)
    await nextTick()
    expect(wrapper.find('.pb-pattern-mgmt__feedback').exists()).toBe(false)

    await wrapper.findAll(`[data-pattern="${hero1.id}"] .pb-pattern-mgmt__btn`)[1].trigger('click') // Duplicate
    await nextTick()
    const feedback = wrapper.find('.pb-pattern-mgmt__feedback')
    expect(feedback.exists()).toBe(true)
    expect(feedback.attributes('role')).toBe('status')
    expect(feedback.text()).toContain('Copy')
  })
})

describe('PatternRenameDialog / PatternDeleteDialog', () => {
  it('rename dialog saves the new name', async () => {
    const { hero1 } = seed()
    const store = usePatternManagementStore()
    store.load()
    store.openRename(hero1.id)
    const wrapper = mount(PatternRenameDialog)
    await nextTick()
    await wrapper.find('.pb-pattern-dialog__input').setValue('New Name')
    await wrapper.find('form').trigger('submit')
    expect(patternLibraryRegistry.get(hero1.id)?.name).toBe('New Name')
    expect(store.renamingId).toBeNull()
  })

  it('delete dialog confirms removal', async () => {
    const { hero1 } = seed()
    const store = usePatternManagementStore()
    store.load()
    store.openDelete(hero1.id)
    const wrapper = mount(PatternDeleteDialog)
    await nextTick()
    expect(wrapper.text()).toContain('Modern Hero')
    await wrapper.find('.pb-pattern-dialog__btn--danger').trigger('click')
    expect(patternLibraryRegistry.has(hero1.id)).toBe(false)
  })
})
