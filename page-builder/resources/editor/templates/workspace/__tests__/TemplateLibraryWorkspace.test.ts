// Phase 26.34 — Template Library Admin Workspace: browses the library, filters, states, and routes
// "Use template" through the usage workflow → the existing apply confirmation. It never applies,
// inserts, or dispatches a command.

import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import {
  builtinTemplateLibrary,
  importedTemplateLibrary,
  type TemplateLibraryItem,
} from '../../library'
import { useTemplateApplyStore } from '../../apply'
import {
  useTemplateWorkspaceStore,
  TemplateLibraryWorkspace,
  WorkspaceTemplateCard,
  filterWorkspace,
} from '..'

function libItem(over: Partial<TemplateLibraryItem> = {}): TemplateLibraryItem {
  return {
    id: 'builtin:b1',
    templateId: 'b1',
    name: 'Bold Hero',
    category: 'hero',
    source: 'builtin',
    ...over,
  }
}

function seedLibrary(): void {
  builtinTemplateLibrary.register(libItem({ templateId: 'b1', name: 'Bold Hero', category: 'hero' }))
  importedTemplateLibrary.register(
    libItem({ templateId: 'i1', name: 'Imported Landing', category: 'landing' }),
  )
}

beforeEach(() => {
  setActivePinia(createPinia())
  builtinTemplateLibrary.clear()
  importedTemplateLibrary.clear()
})

afterEach(() => {
  builtinTemplateLibrary.clear()
  importedTemplateLibrary.clear()
})

describe('filterWorkspace', () => {
  const items = [
    libItem({ id: 'builtin:b1', templateId: 'b1', source: 'builtin', category: 'hero', name: 'Bold Hero' }),
    libItem({ id: 'imported:i1', templateId: 'i1', source: 'imported', category: 'landing', name: 'Imported Landing' }),
  ]

  it('applies source, category, and search', () => {
    expect(filterWorkspace(items, { source: 'imported' }).map((i) => i.templateId)).toEqual(['i1'])
    expect(filterWorkspace(items, { category: 'hero' }).map((i) => i.templateId)).toEqual(['b1'])
    expect(filterWorkspace(items, { query: 'landing' }).map((i) => i.templateId)).toEqual(['i1'])
    expect(filterWorkspace(items, {})).toHaveLength(2)
  })
})

describe('useTemplateWorkspaceStore', () => {
  it('loads from the Template Library and exposes filters + counts', () => {
    seedLibrary()
    const store = useTemplateWorkspaceStore()
    store.load()

    expect(store.loaded).toBe(true)
    expect(store.results).toHaveLength(2)
    expect(store.sources.map((s) => s.key).sort()).toEqual(['builtin', 'imported'])
    store.setSource('imported')
    expect(store.results.map((i) => i.templateId)).toEqual(['i1'])
  })

  it('drives empty and no-results states', () => {
    const empty = useTemplateWorkspaceStore()
    empty.load()
    expect(empty.isEmpty).toBe(true)

    seedLibrary()
    const store = useTemplateWorkspaceStore()
    store.load()
    store.setQuery('nothing-matches')
    expect(store.noResults).toBe(true)
  })

  it('use() routes through the usage workflow → apply confirmation (never applies)', () => {
    seedLibrary()
    const store = useTemplateWorkspaceStore()
    store.load()
    const apply = useTemplateApplyStore()

    store.use(store.results[0])
    expect(apply.isOpen).toBe(true) // handed off to the existing confirm flow
    expect(apply.pending?.templateId).toBe('b1')
    expect(apply.lastAppliedId).toBeNull() // but NOT applied — no insert-pattern, no BuilderState
  })
})

describe('TemplateLibraryWorkspace.vue', () => {
  it('renders a card per result once loaded', async () => {
    seedLibrary()
    const wrapper = mount(TemplateLibraryWorkspace)
    await nextTick() // onMounted → load

    expect(wrapper.findAllComponents(WorkspaceTemplateCard)).toHaveLength(2)
  })

  it('shows the empty state when the library has no templates', async () => {
    const wrapper = mount(TemplateLibraryWorkspace)
    await nextTick()

    expect(wrapper.findAllComponents(WorkspaceTemplateCard)).toHaveLength(0)
    expect(wrapper.find('.pb-tpl-workspace__state').text()).toContain('No templates yet')
  })

  it('clicking "Use template" hands off to the apply confirmation', async () => {
    seedLibrary()
    const wrapper = mount(TemplateLibraryWorkspace)
    await nextTick()

    await wrapper.findComponent(WorkspaceTemplateCard).find('.pb-tpl-ws-card__use').trigger('click')
    expect(useTemplateApplyStore().isOpen).toBe(true)
  })
})
