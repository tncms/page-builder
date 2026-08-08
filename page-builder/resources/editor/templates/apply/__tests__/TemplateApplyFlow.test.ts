// Phase 26.23 — Template Apply UX (Picker selection → confirm → runtime apply).
//
// Verifies the intent seam, the confirmation store (request/confirm/cancel/error), the dialog,
// and the browse+apply container. Regression: apply goes ONLY through the runtime (one
// insert-pattern command, one undo), never writes BuilderState directly, and a failure is atomic
// (no command, no mutation) and surfaced as an error state.

import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { nextTick } from 'vue'
import { mount, flushPromises } from '@vue/test-utils'

import { useEditorStore } from '../../../stores/editor'
import { localTemplateProvider } from '../../provider'
import { patternProvider } from '../../../patterns/provider'
import { builtinTemplateLibrary, importedTemplateLibrary, type TemplateLibraryItem } from '../../library'
import { useTemplatePickerStore } from '../../picker'
import { createPatternDefinition, patternLibraryRegistry, type PatternTree } from '../../../patterns/library'
import type { TemplateDefinition } from '../../contracts'
import type { SectionNode, LayoutPayload } from '../../../types'

import { applyTemplateSelection } from '../applyTemplateIntent'
import { useTemplateApplyStore } from '../templateApplyStore'
import TemplateApplyDialog from '../TemplateApplyDialog.vue'
import TemplateBrowser from '../TemplateBrowser.vue'

function section(type = 'section'): SectionNode {
  return { type, data: { background: '#fff' }, blocks: [{ type: 'heading', data: {} }] }
}
function pageTree(): PatternTree {
  return createPatternDefinition({ name: 'x', sections: [section()] }).tree
}
function userTemplate(id = 'u1', name = 'User Hero'): TemplateDefinition {
  return { id, name, layout: { kind: 'tree', tree: pageTree() } }
}
function libItem(over: Partial<TemplateLibraryItem> = {}): TemplateLibraryItem {
  return { id: 'user:u1', templateId: 'u1', name: 'User Hero', category: 'hero', source: 'user', ...over }
}
function selection(item: Partial<TemplateLibraryItem> = {}) {
  const full = libItem(item)
  return { templateId: full.templateId, source: full.source, item: full }
}

// A faithful `insert-pattern` fake (mirrors the runtime apply test): server re-keys + selects root.
function seedEditor() {
  const editor = useEditorStore()
  editor.currentPayload = { schema: 'pagebuilder/01', sections: [] } as LayoutPayload
  let n = 0
  const rekey = (node: SectionNode): SectionNode => {
    const id = `srv-${(n += 1)}`
    const data = { ...node.data }
    if (Array.isArray(data.blocks)) data.blocks = (data.blocks as SectionNode[]).map(rekey)
    return { ...node, id, data, ...(node.blocks ? { blocks: node.blocks.map(rekey) } : {}) }
  }
  const runCommand = vi.fn(async (before: LayoutPayload, command: string, params: Record<string, unknown>) => {
    if (command !== 'insert-pattern') return { payload: before, selection: { selected: null } }
    const added = (params.sections as SectionNode[]).map(rekey)
    return { payload: { ...before, sections: [...before.sections, ...added] }, selection: { selected: added[0].id } }
  })
  ;(editor as unknown as { client: unknown }).client = { runCommand }
  return { editor, runCommand }
}

beforeEach(() => {
  setActivePinia(createPinia())
  patternLibraryRegistry.clear()
  localTemplateProvider.clear()
  builtinTemplateLibrary.clear()
  importedTemplateLibrary.clear()
})

// ---- intent -----------------------------------------------------------------

describe('applyTemplateSelection', () => {
  it('applies a selection through the runtime (one insert-pattern command)', async () => {
    const { editor, runCommand } = seedEditor()
    localTemplateProvider.register(userTemplate())
    const result = await applyTemplateSelection(selection(), editor)
    expect(result.ok).toBe(true)
    expect(runCommand).toHaveBeenCalledTimes(1)
    expect(runCommand.mock.calls[0][1]).toBe('insert-pattern')
    expect(editor.undoStack).toHaveLength(1)
  })

  it('surfaces template_definition_not_found for an unknown id (no command)', async () => {
    const { editor, runCommand } = seedEditor()
    await expect(applyTemplateSelection(selection({ templateId: 'ghost', id: 'user:ghost' }), editor)).rejects.toMatchObject({
      code: 'template_definition_not_found',
    })
    expect(runCommand).not.toHaveBeenCalled()
  })
})

// ---- store flow -------------------------------------------------------------

describe('useTemplateApplyStore', () => {
  it('request → confirm applies via the runtime and reports feedback', async () => {
    const { editor, runCommand } = seedEditor()
    localTemplateProvider.register(userTemplate())
    const store = useTemplateApplyStore()

    store.request(selection())
    expect(store.isOpen).toBe(true)

    const ok = await store.confirm()
    expect(ok).toBe(true)
    expect(runCommand).toHaveBeenCalledTimes(1)
    expect(editor.currentPayload.sections).toHaveLength(1) // inserted
    expect(editor.undoStack).toHaveLength(1) // one undo entry
    expect(store.feedback).toContain('User Hero')
    expect(store.lastAppliedId).toBe('u1')
    expect(store.isOpen).toBe(false) // closed on success
    expect(store.error).toBeNull()
  })

  it('cancel closes without applying', async () => {
    const { runCommand } = seedEditor()
    const store = useTemplateApplyStore()
    store.request(selection())
    store.cancel()
    expect(store.isOpen).toBe(false)
    expect(runCommand).not.toHaveBeenCalled()
  })

  it('isolates an unknown-template failure into an error state (atomic, no mutation)', async () => {
    const { editor, runCommand } = seedEditor()
    const before = JSON.stringify(editor.currentPayload)
    const store = useTemplateApplyStore()
    store.request(selection({ templateId: 'ghost', id: 'user:ghost' }))

    const ok = await store.confirm()
    expect(ok).toBe(false)
    expect(store.error).toContain('ghost')
    expect(runCommand).not.toHaveBeenCalled()
    expect(editor.undoStack).toHaveLength(0)
    expect(JSON.stringify(editor.currentPayload)).toBe(before) // BuilderState untouched
    expect(store.isOpen).toBe(true) // stays open so the user can retry/cancel
  })

  it('a resolution failure (missing pattern) is atomic — no command, error surfaced', async () => {
    const { editor, runCommand } = seedEditor()
    localTemplateProvider.register({ id: 'bad', name: 'Bad', layout: { kind: 'patterns', patternIds: ['missing'] } })
    const store = useTemplateApplyStore()
    store.request(selection({ templateId: 'bad', id: 'user:bad', name: 'Bad' }))

    expect(await store.confirm()).toBe(false)
    expect(runCommand).not.toHaveBeenCalled()
    expect(editor.undoStack).toHaveLength(0)
    expect(store.error).toBeTruthy()
  })
})

// ---- dialog -----------------------------------------------------------------

describe('TemplateApplyDialog.vue', () => {
  it('renders the pending metadata and confirms/cancels through the store', async () => {
    seedEditor()
    localTemplateProvider.register(userTemplate())
    const store = useTemplateApplyStore()
    const wrapper = mount(TemplateApplyDialog)

    expect(wrapper.find('.pb-tpl-apply').exists()).toBe(false) // closed initially
    store.request(selection())
    await nextTick()
    expect(wrapper.find('.pb-tpl-apply__name').text()).toBe('User Hero')
    expect(wrapper.findAll('.pb-tpl-apply__tag').map((t) => t.text())).toEqual(['user', 'hero'])

    await wrapper.find('.pb-tpl-apply__btn--primary').trigger('click')
    await flushPromises()
    expect(store.lastAppliedId).toBe('u1')
    expect(wrapper.find('.pb-tpl-apply').exists()).toBe(false) // closed after apply
  })

  // Phase 26.39 RC a11y hardening — Escape must dismiss the dialog (was missing).
  it('closes on Escape without applying', async () => {
    seedEditor()
    localTemplateProvider.register(userTemplate())
    const store = useTemplateApplyStore()
    const wrapper = mount(TemplateApplyDialog)
    store.request(selection())
    await nextTick()

    await wrapper.find('.pb-tpl-apply__panel').trigger('keydown.esc')
    expect(store.isOpen).toBe(false)
    expect(store.lastAppliedId).toBeNull() // dismissed, not applied
  })
})

// ---- browse + apply container ----------------------------------------------

describe('TemplateBrowser.vue', () => {
  it('offers Apply for a picked template and applies it end-to-end', async () => {
    const { runCommand } = seedEditor()
    localTemplateProvider.register(userTemplate())
    const wrapper = mount(TemplateBrowser)
    await nextTick()

    // No selection yet → no apply bar.
    expect(wrapper.find('.pb-tpl-browser__bar').exists()).toBe(false)

    // Select the user template in the picker (the 26.22 handoff).
    useTemplatePickerStore().select('user:u1')
    await nextTick()
    expect(wrapper.find('.pb-tpl-browser__bar').exists()).toBe(true)

    // Apply → opens the dialog → confirm applies via the runtime.
    await wrapper.find('.pb-tpl-browser__apply').trigger('click')
    await nextTick()
    expect(wrapper.find('.pb-tpl-apply').exists()).toBe(true)
    await wrapper.find('.pb-tpl-apply__btn--primary').trigger('click')
    await flushPromises()

    expect(runCommand).toHaveBeenCalledTimes(1)
    expect(wrapper.find('.pb-tpl-browser__feedback').text()).toContain('User Hero')
  })
})

// ---- regression -------------------------------------------------------------

describe('regression — pattern insertion pipeline unchanged', () => {
  it('PatternProvider still exposes its full surface', () => {
    for (const method of ['all', 'get', 'register', 'update', 'duplicate', 'remove'] as const) {
      expect(typeof patternProvider[method]).toBe('function')
    }
  })
})
