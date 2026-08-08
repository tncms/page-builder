// Phase 26.19 — Template Management Foundation.
//
// Verifies: rename (via provider.update, id preserved), duplicate (fresh id, independent,
// composed from get+register — no provider.duplicate), delete (provider.remove), search +
// category-KEY filter, provider-only mutation, managed templates still resolving through the
// runtime, and Pattern v1 remaining untouched.

import { describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { mount } from '@vue/test-utils'

import { useTemplateManagementStore } from '../templateManagementStore'
import {
  renameTemplate,
  duplicateTemplate,
  deleteTemplate,
  searchTemplates,
  templateSummary,
} from '../templateActions'
import TemplateManagementPanel from '../TemplateManagementPanel.vue'
import { localTemplateProvider } from '../../provider'
import { resolveTemplate } from '../../runtime'
import { patternLibraryRegistry, type PatternTree } from '../../../patterns/library'
import type { SectionNode } from '../../../types'
import type { TemplateDefinition } from '../../contracts'

function section(type = 'section'): SectionNode {
  return { type, data: { background: '#fff' }, blocks: [{ type: 'heading', data: {} }] }
}
function tree(sections = [section()]): PatternTree {
  return { sections }
}

let seq = 0
/** Register a tree-layout template directly through the provider (the authoring output). */
function register(name: string, category = 'page', sections = [section()]): TemplateDefinition {
  const def: TemplateDefinition = { id: `tpl-${(seq += 1)}`, name, category, layout: { kind: 'tree', tree: tree(sections) } }
  return localTemplateProvider.register(def)
}

beforeEach(() => {
  setActivePinia(createPinia())
  localTemplateProvider.clear()
  seq = 0
})

// ---------------------------------------------------------------------------
// Rename
// ---------------------------------------------------------------------------
describe('rename', () => {
  it('renames in place, preserving id and list position', () => {
    register('First')
    const b = register('Second')
    const result = renameTemplate(b.id, ' Renamed ')

    expect(result.ok).toBe(true)
    expect(result.template!.id).toBe(b.id)
    expect(result.template!.name).toBe('Renamed') // trimmed
    // Position preserved (still second).
    const all = localTemplateProvider.all()
    expect(all.map((t) => t.name)).toEqual(['First', 'Renamed'])
  })

  it('rejects an empty name and an unknown id', () => {
    const t = register('Keep')
    expect(renameTemplate(t.id, '   ').ok).toBe(false)
    expect(localTemplateProvider.get(t.id)!.name).toBe('Keep') // unchanged
    expect(renameTemplate('nope', 'X').ok).toBe(false)
  })
})

// ---------------------------------------------------------------------------
// Duplicate (composed get + register — no provider.duplicate)
// ---------------------------------------------------------------------------
describe('duplicate', () => {
  it('creates an independent copy with a fresh id and "Copy" name', () => {
    const source = register('Hero', 'landing')
    const copy = duplicateTemplate(source.id)!

    expect(copy.id).not.toBe(source.id)
    expect(copy.name).toBe('Hero Copy')
    expect(copy.category).toBe('landing')
    expect(localTemplateProvider.all()).toHaveLength(2)
  })

  it('does not alias the original — mutating the copy leaves the source intact', () => {
    const source = register('Hero')
    const copy = duplicateTemplate(source.id)!

    // Mutate the copy's stored tree via a fresh update, then re-read the source.
    const copyTree = (copy.layout as { tree: PatternTree }).tree
    copyTree.sections[0].data.background = '#000'
    localTemplateProvider.update(copy.id, { layout: { kind: 'tree', tree: copyTree } })

    const sourceTree = (localTemplateProvider.get(source.id)!.layout as { tree: PatternTree }).tree
    expect(sourceTree.sections[0].data.background).toBe('#fff')
  })

  it('returns null for an unknown id', () => {
    expect(duplicateTemplate('missing')).toBeNull()
  })
})

// ---------------------------------------------------------------------------
// Delete
// ---------------------------------------------------------------------------
describe('delete', () => {
  it('removes only the target template', () => {
    const a = register('A')
    register('B')
    expect(deleteTemplate(a.id)).toBe(true)
    expect(localTemplateProvider.all().map((t) => t.name)).toEqual(['B'])
  })
})

// ---------------------------------------------------------------------------
// Search + category KEY filter (pure)
// ---------------------------------------------------------------------------
describe('search + category filter', () => {
  it('filters by name/description substring and by category key', () => {
    const list: TemplateDefinition[] = [
      { id: '1', name: 'Landing Hero', category: 'landing', layout: { kind: 'tree', tree: tree() } },
      { id: '2', name: 'Blog Post', category: 'post', description: 'article layout', layout: { kind: 'tree', tree: tree() } },
    ]
    expect(searchTemplates(list, 'hero', null).map((t) => t.id)).toEqual(['1'])
    expect(searchTemplates(list, 'article', null).map((t) => t.id)).toEqual(['2']) // description match
    expect(searchTemplates(list, '', 'post').map((t) => t.id)).toEqual(['2']) // category key
    expect(searchTemplates(list, 'hero', 'post')).toHaveLength(0) // both must match
  })

  it('templateSummary reports sections for tree and patterns for pattern layouts', () => {
    expect(templateSummary({ id: 'a', name: 'A', layout: { kind: 'tree', tree: tree([section(), section()]) } })).toBe('2 sections')
    expect(templateSummary({ id: 'b', name: 'B', layout: { kind: 'patterns', patternIds: ['p1'] } })).toBe('1 pattern')
  })
})

// ---------------------------------------------------------------------------
// Store: provider-only mutation + runtime read-back
// ---------------------------------------------------------------------------
describe('management store', () => {
  it('rename/duplicate/delete flow through the store and re-snapshot', () => {
    register('One')
    const store = useTemplateManagementStore()
    store.load()
    expect(store.results).toHaveLength(1)

    // Duplicate → 2 templates, immediate refresh.
    store.duplicate(store.results[0].id)
    expect(store.results).toHaveLength(2)

    // Rename the copy.
    const copy = store.results.find((t) => t.name.endsWith('Copy'))!
    store.openRename(copy.id)
    store.setRenameDraft('Renamed Copy')
    expect(store.confirmRename()).toBe(true)
    expect(store.results.some((t) => t.name === 'Renamed Copy')).toBe(true)

    // Delete the original.
    const original = store.results.find((t) => t.name === 'One')!
    store.openDelete(original.id)
    expect(store.confirmDelete()).toBe(true)
    expect(store.results).toHaveLength(1)
  })

  it('categories getter reports only category keys with a count', () => {
    register('A', 'page')
    register('B', 'landing')
    register('C', 'page')
    const store = useTemplateManagementStore()
    store.load()
    const map = Object.fromEntries(store.categories.map((c) => [c.key, c.count]))
    expect(map).toEqual({ page: 2, landing: 1 })
  })

  it('a managed template still resolves through the Template Runtime', () => {
    const t = register('Landing')
    const copy = duplicateTemplate(t.id)!
    const resolved = resolveTemplate(copy)
    expect(resolved.source).toBe('tree')
    expect(resolved.tree.sections).toHaveLength(1)
  })
})

// ---------------------------------------------------------------------------
// Pattern v1 untouched
// ---------------------------------------------------------------------------
describe('Pattern v1 unchanged', () => {
  it('template management never touches the pattern registry', () => {
    const t = register('X')
    duplicateTemplate(t.id)
    renameTemplate(t.id, 'Y')
    deleteTemplate(t.id)
    expect(patternLibraryRegistry.all()).toHaveLength(0)
  })
})

// ---------------------------------------------------------------------------
// Panel
// ---------------------------------------------------------------------------
describe('TemplateManagementPanel', () => {
  it('renders the empty state when no templates exist', async () => {
    const wrapper = mount(TemplateManagementPanel)
    await wrapper.vm.$nextTick()
    expect(wrapper.find('.pb-template-mgmt').exists()).toBe(true)
    expect(wrapper.text()).toContain('No templates saved yet.')
  })

  it('lists registered templates with a summary and actions', async () => {
    register('Landing Hero', 'landing')
    const wrapper = mount(TemplateManagementPanel)
    await wrapper.vm.$nextTick()
    expect(wrapper.text()).toContain('Landing Hero')
    expect(wrapper.text()).toContain('1 section')
    expect(wrapper.findAll('.pb-template-mgmt__item')).toHaveLength(1)
  })
})
