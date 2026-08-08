// Phase 26.22 — Template Picker Foundation.
//
// Browse / search / filter / SELECT over the Phase 26.21 Template Library. Verifies pure filter
// composition, store behavior (load/results/counts/selection/empty/no-results/error), and the
// component (chips, cards, thumbnail-from-URL, selection state). Regression: the picker consumes
// ONLY the library, records selection as identity metadata, and never applies / mutates a
// provider / carries a layout tree.

import { describe, it, expect, beforeEach, vi, afterEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { nextTick } from 'vue'
import { mount } from '@vue/test-utils'

import {
  builtinTemplateLibrary,
  importedTemplateLibrary,
  templateLibrary,
  type TemplateLibraryItem,
} from '../../library'
import { localTemplateProvider } from '../../provider'
import { createPatternDefinition, type PatternTree } from '../../../patterns/library'
import type { TemplateDefinition } from '../../contracts'

import { filterLibrary, libraryCategoryCounts, librarySourceCounts } from '../templatePickerFilters'
import { useTemplatePickerStore } from '../templatePickerStore'
import TemplatePicker from '../TemplatePicker.vue'
import TemplateCard from '../TemplateCard.vue'

function pageTree(): PatternTree {
  return createPatternDefinition({
    name: 'x',
    sections: [{ type: 'section', data: {}, blocks: [{ type: 'heading', data: {} }] }],
  }).tree
}

function tpl(over: Partial<TemplateDefinition> = {}): TemplateDefinition {
  return { id: 't1', name: 'T1', layout: { kind: 'tree', tree: pageTree() }, ...over }
}

function libItem(over: Partial<TemplateLibraryItem> = {}): TemplateLibraryItem {
  return { id: '', templateId: 'x1', name: 'X', category: 'hero', source: 'ignored', ...over }
}

/** Seed all three active sources so the default `templateLibrary` composite has content. */
function seedLibrary(): void {
  builtinTemplateLibrary.register(libItem({ templateId: 'b1', name: 'Bold Hero', category: 'hero' }))
  importedTemplateLibrary.register(
    libItem({ templateId: 'i1', name: 'Imported Landing', category: 'landing', thumbnail: 'https://cdn.example.com/i.webp' }),
  )
  localTemplateProvider.register(tpl({ id: 'u1', name: 'User Hero', category: 'hero', description: 'mine' }))
}

beforeEach(() => {
  setActivePinia(createPinia())
  builtinTemplateLibrary.clear()
  importedTemplateLibrary.clear()
  localTemplateProvider.clear()
})

afterEach(() => {
  vi.restoreAllMocks()
})

// ---- pure filters -----------------------------------------------------------

describe('templatePickerFilters', () => {
  const items = [
    libItem({ id: 'builtin:b1', source: 'builtin', category: 'hero', name: 'Bold Hero' }),
    libItem({ id: 'user:u1', source: 'user', category: 'hero', name: 'User Hero' }),
    libItem({ id: 'imported:i1', source: 'imported', category: 'landing', name: 'Landing' }),
  ]

  it('composes source → category → search', () => {
    expect(filterLibrary(items, { source: null, category: null, query: '' }).map((i) => i.id)).toEqual([
      'builtin:b1',
      'user:u1',
      'imported:i1',
    ])
    expect(filterLibrary(items, { source: 'user', category: null, query: '' }).map((i) => i.id)).toEqual(['user:u1'])
    expect(filterLibrary(items, { source: null, category: 'hero', query: '' }).map((i) => i.id)).toEqual([
      'builtin:b1',
      'user:u1',
    ])
    expect(filterLibrary(items, { source: null, category: 'hero', query: 'bold' }).map((i) => i.id)).toEqual([
      'builtin:b1',
    ])
  })

  it('counts by category and source KEYS', () => {
    expect(libraryCategoryCounts(items)).toEqual({ hero: 2, landing: 1 })
    expect(librarySourceCounts(items)).toEqual({ builtin: 1, user: 1, imported: 1 })
  })
})

// ---- store ------------------------------------------------------------------

describe('useTemplatePickerStore', () => {
  it('loads ONLY from the template library composite', () => {
    seedLibrary()
    const store = useTemplatePickerStore()
    expect(store.loaded).toBe(false)
    store.load()
    expect(store.loaded).toBe(true)
    expect(store.results.map((i) => i.id).sort()).toEqual(['builtin:b1', 'imported:i1', 'user:u1'])
    expect(store.error).toBeNull()
  })

  it('derives category + source chip counts', () => {
    seedLibrary()
    const store = useTemplatePickerStore()
    store.load()
    expect(store.categories).toEqual(expect.arrayContaining([{ key: 'hero', count: 2 }, { key: 'landing', count: 1 }]))
    expect(store.sources).toEqual(
      expect.arrayContaining([
        { key: 'builtin', count: 1 },
        { key: 'user', count: 1 },
        { key: 'imported', count: 1 },
      ]),
    )
  })

  it('filters by source, category, and query together', () => {
    seedLibrary()
    const store = useTemplatePickerStore()
    store.load()
    store.setSource('user')
    expect(store.results.map((i) => i.id)).toEqual(['user:u1'])
    store.setSource(null)
    store.setCategory('hero')
    expect(store.results.map((i) => i.templateId).sort()).toEqual(['b1', 'u1'])
    store.setQuery('bold')
    expect(store.results.map((i) => i.templateId)).toEqual(['b1'])
  })

  it('records a selection as identity metadata only — no layout tree', () => {
    seedLibrary()
    const store = useTemplatePickerStore()
    store.load()
    store.select('user:u1')
    expect(store.selectedItem?.name).toBe('User Hero')
    expect(store.selection).toMatchObject({ templateId: 'u1', source: 'user' })
    expect((store.selection as Record<string, unknown>).tree).toBeUndefined()
    expect((store.selection!.item as Record<string, unknown>).layout).toBeUndefined()
    store.clearSelection()
    expect(store.selection).toBeNull()
  })

  it('exposes empty and no-results states', () => {
    const store = useTemplatePickerStore()
    store.load()
    expect(store.isEmpty).toBe(true)
    seedLibrary()
    store.load()
    store.setQuery('zzz-nomatch')
    expect(store.isEmpty).toBe(false)
    expect(store.noResults).toBe(true)
  })

  it('isolates a library failure into an error state', () => {
    const spy = vi.spyOn(templateLibrary, 'all').mockImplementation(() => {
      throw new Error('boom')
    })
    const store = useTemplatePickerStore()
    store.load()
    expect(store.error).toBe('boom')
    expect(store.results).toEqual([])
    expect(store.isEmpty).toBe(false)
    spy.mockRestore()
  })

  it('does not mutate the underlying providers (discovery only)', () => {
    seedLibrary()
    const store = useTemplatePickerStore()
    store.load()
    store.select('user:u1')
    store.setQuery('hero')
    // User template still fully intact in its provider — nothing was applied or removed.
    expect(localTemplateProvider.get('u1')?.layout.kind).toBe('tree')
    expect(localTemplateProvider.all()).toHaveLength(1)
  })
})

// ---- component --------------------------------------------------------------

describe('TemplatePicker.vue', () => {
  it('renders a card per result with source/category chips', async () => {
    seedLibrary()
    const wrapper = mount(TemplatePicker)
    await nextTick()
    expect(wrapper.findAllComponents(TemplateCard)).toHaveLength(3)
    // All-sources + 3 source chips, All-categories + 2 category chips.
    const chips = wrapper.findAll('.pb-template-picker__chip').map((c) => c.text())
    expect(chips.some((t) => t.includes('builtin'))).toBe(true)
    expect(chips.some((t) => t.includes('hero'))).toBe(true)
  })

  it('renders a thumbnail URL verbatim and a placeholder otherwise', async () => {
    seedLibrary()
    const wrapper = mount(TemplatePicker)
    await nextTick()
    const imgs = wrapper.findAll('.pb-tpl-card__thumb')
    expect(imgs.map((i) => i.attributes('src'))).toContain('https://cdn.example.com/i.webp')
    // The two thumbnail-less items fall back to an initial placeholder.
    expect(wrapper.findAll('.pb-tpl-card__initial').length).toBe(2)
  })

  it('selects a card on click and reflects the selected state', async () => {
    seedLibrary()
    const wrapper = mount(TemplatePicker)
    await nextTick()
    const store = useTemplatePickerStore()
    await wrapper.findComponent(TemplateCard).find('.pb-tpl-card').trigger('click')
    expect(store.selectedId).not.toBeNull()
    await nextTick()
    expect(wrapper.find('.pb-tpl-card--selected').exists()).toBe(true)
  })

  it('shows the empty state with no templates', async () => {
    const wrapper = mount(TemplatePicker)
    await nextTick()
    expect(wrapper.find('.pb-template-picker__state').text()).toContain('No templates yet')
    expect(wrapper.findComponent(TemplateCard).exists()).toBe(false)
  })
})
