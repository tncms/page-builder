// Phase 26.12 — Pattern Picker & Insertion UX.
//
// Covers the picker store (registry loading, category filtering, empty state, insertion
// through the foundation), the history contract (one undo entry, redo restores), and the
// picker/card components (empty state copy, category chips, metadata summary, insert
// intent). Insertion always routes through `insertPattern` → the existing command
// pipeline; the picker never calls `executeCommand` or mutates selection directly.

import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { mount, flushPromises } from '@vue/test-utils'
import { nextTick } from 'vue'

import { usePatternPickerStore } from '../patternPickerStore'
import { useEditorStore } from '../../../stores/editor'
import { patternLibraryRegistry, createPatternDefinition } from '../../library'
import { patternComponentCount, patternComponentSummary, filterByCategory } from '../patternFilters'
import PatternPicker from '../PatternPicker.vue'
import PatternCard from '../PatternCard.vue'
import type { SectionNode, LayoutPayload } from '../../../types'

// A hero section: heading + button + image → 3 components.
function heroSection(): SectionNode {
  return {
    id: 'sec-1',
    type: 'section',
    data: { background: '#fff' },
    blocks: [
      { id: 'h1', type: 'heading', data: { text: 'Hi' } },
      { id: 'b1', type: 'button', data: { label: 'Go' } },
      { id: 'i1', type: 'image', data: { src: '' } },
    ],
  }
}

function seedRegistry(): void {
  patternLibraryRegistry.clear()
  patternLibraryRegistry.register(createPatternDefinition({ id: 'hero.a', name: 'Hero Landing', category: 'hero', description: 'A hero', sections: [heroSection()] }))
  patternLibraryRegistry.register(createPatternDefinition({ id: 'hero.b', name: 'Hero Two', category: 'hero', sections: [heroSection()] }))
  patternLibraryRegistry.register(createPatternDefinition({ id: 'cta.a', name: 'CTA One', category: 'cta', sections: [heroSection()] }))
}

// A faithful `insert-pattern` fake: assign server ids recursively, select the new root.
function seedEditorInsert() {
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
    const payload = { ...before, sections: [...before.sections, ...added] }
    return { payload, selection: { selected: added[0].id } }
  })
  ;(editor as unknown as { client: unknown }).client = { runCommand }
  return { editor, runCommand }
}

beforeEach(() => {
  setActivePinia(createPinia())
  seedRegistry()
})

// ---- Filters (pure) ----------------------------------------------------------

describe('patternFilters', () => {
  it('counts and summarizes a pattern by its block types', () => {
    const def = createPatternDefinition({ name: 'H', sections: [heroSection()] })
    expect(patternComponentCount(def)).toBe(3)
    expect(patternComponentSummary(def)).toEqual(['heading', 'button', 'image'])
  })

  it('filters by category, null = all', () => {
    const all = patternLibraryRegistry.all()
    expect(filterByCategory(all, null)).toHaveLength(3)
    expect(filterByCategory(all, 'hero').map((p) => p.id)).toEqual(['hero.a', 'hero.b'])
    expect(filterByCategory(all, 'cta').map((p) => p.id)).toEqual(['cta.a'])
  })
})

// ---- Store -------------------------------------------------------------------

describe('patternPickerStore', () => {
  it('loads patterns from the registry', () => {
    const store = usePatternPickerStore()
    expect(store.loaded).toBe(false)
    store.load()
    expect(store.loaded).toBe(true)
    expect(store.patterns).toHaveLength(3)
  })

  it('filters results by category and lists categories with counts', () => {
    const store = usePatternPickerStore()
    store.load()
    expect(store.results).toHaveLength(3)
    store.setCategory('hero')
    expect(store.results.map((p) => p.id)).toEqual(['hero.a', 'hero.b'])
    expect(store.categories).toEqual([{ key: 'hero', count: 2 }, { key: 'cta', count: 1 }])
  })

  it('reports the empty state when the registry has no patterns', () => {
    patternLibraryRegistry.clear()
    const store = usePatternPickerStore()
    store.load()
    expect(store.isEmpty).toBe(true)
    expect(store.results).toHaveLength(0)
  })

  it('opens the metadata preview for a selected pattern', () => {
    const store = usePatternPickerStore()
    store.load()
    store.select('hero.a')
    expect(store.selectedPattern?.name).toBe('Hero Landing')
    store.select(null)
    expect(store.selectedPattern).toBeUndefined()
  })

  it('inserts a pattern through the foundation with one command', async () => {
    const { runCommand } = seedEditorInsert()
    const store = usePatternPickerStore()
    store.load()

    const ok = await store.insert('hero.a')
    expect(ok).toBe(true)
    expect(runCommand).toHaveBeenCalledTimes(1)
    expect(runCommand.mock.calls[0][1]).toBe('insert-pattern')
  })

  it('does nothing for an invalid selection (no command)', async () => {
    const { runCommand } = seedEditorInsert()
    const store = usePatternPickerStore()
    store.load()

    const ok = await store.insert('does.not.exist')
    expect(ok).toBe(false)
    expect(runCommand).not.toHaveBeenCalled()
  })

  it('updates selection to the inserted root and preserves selection architecture', async () => {
    const { editor } = seedEditorInsert()
    const store = usePatternPickerStore()
    store.load()
    await store.insert('hero.a')
    expect(editor.selectedComponentId).toBe('srv-1') // new root selected server-side
  })
})

// ---- History: one undo entry, undo removes, redo restores --------------------

describe('pattern insertion history', () => {
  it('is one undo entry; undo removes the tree and redo restores it', async () => {
    const { editor } = seedEditorInsert()
    const store = usePatternPickerStore()
    store.load()

    await store.insert('hero.a')
    expect(editor.undoStack).toHaveLength(1) // exactly one undo entry
    expect(editor.currentPayload.sections).toHaveLength(1)

    editor.undo()
    expect(editor.currentPayload.sections).toHaveLength(0) // inserted tree removed

    editor.redo()
    expect(editor.currentPayload.sections).toHaveLength(1) // same tree restored
    expect(editor.currentPayload.sections[0].type).toBe('section')
  })
})

// ---- Components --------------------------------------------------------------

describe('PatternCard', () => {
  it('renders name, category, count and type summary; emits insert/select', async () => {
    const pattern = createPatternDefinition({ id: 'hero.a', name: 'Hero Landing', category: 'hero', sections: [heroSection()] })
    const wrapper = mount(PatternCard, { props: { pattern } })

    expect(wrapper.find('.pb-picker-card__title').text()).toBe('Hero Landing')
    expect(wrapper.find('.pb-picker-card__cat').text()).toBe('hero')
    expect(wrapper.find('.pb-picker-card__count').text()).toContain('3 components')
    expect(wrapper.findAll('.pb-picker-card__type').map((t) => t.text())).toEqual(['heading', 'button', 'image'])

    await wrapper.find('.pb-picker-btn--primary').trigger('click')
    expect(wrapper.emitted('insert')).toBeTruthy()
    await wrapper.find('.pb-picker-card__preview').trigger('click')
    expect(wrapper.emitted('select')).toBeTruthy()
  })

  it('disables insert while inserting', () => {
    const pattern = createPatternDefinition({ name: 'X', sections: [heroSection()] })
    const wrapper = mount(PatternCard, { props: { pattern, inserting: true } })
    expect(wrapper.find('.pb-picker-btn--primary').attributes('disabled')).toBeDefined()
  })
})

describe('PatternPicker', () => {
  it('shows the empty state when no patterns exist', async () => {
    patternLibraryRegistry.clear()
    const wrapper = mount(PatternPicker)
    await nextTick() // onMounted → store.load()
    expect(wrapper.find('.pb-pattern-picker__empty-title').text()).toBe('No patterns yet.')
    expect(wrapper.find('.pb-pattern-picker__empty-hint').text()).toContain('Save a layout to create your first reusable pattern')
    expect(wrapper.find('.pb-pattern-picker__grid').exists()).toBe(false)
  })

  it('lists patterns and category chips, and filters by category', async () => {
    const wrapper = mount(PatternPicker)
    await nextTick() // onMounted → store.load()
    expect(wrapper.findAllComponents(PatternCard)).toHaveLength(3)

    const chips = wrapper.findAll('.pb-picker-chip')
    expect(chips.map((c) => c.text().replace(/\s+\d+$/, '').trim())).toEqual(['All', 'hero', 'cta'])

    await wrapper.findAll('.pb-picker-chip')[1].trigger('click') // hero
    expect(wrapper.findAllComponents(PatternCard)).toHaveLength(2)
  })

  it('inserts through the store when a card requests it', async () => {
    seedEditorInsert()
    const wrapper = mount(PatternPicker)
    await nextTick() // onMounted → store.load()
    const store = usePatternPickerStore()
    const insert = vi.spyOn(store, 'insert').mockResolvedValue(true)

    await wrapper.findAllComponents(PatternCard)[0].vm.$emit('insert')
    await flushPromises()
    expect(insert).toHaveBeenCalledWith('hero.a')
  })
})
