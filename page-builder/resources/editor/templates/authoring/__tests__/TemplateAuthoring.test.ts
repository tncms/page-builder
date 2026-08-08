// Phase 26.18 — Template Authoring Foundation.
//
// Verifies: section/container/page capture, invalid-root rejection, source tree left
// unchanged, ids stripped, metadata validation, provider registration (unique ids), the
// created template resolving through the Template Runtime, and Pattern v1 remaining untouched.

import { describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { mount } from '@vue/test-utils'

import { useTemplateAuthoringStore } from '../templateAuthoringStore'
import { buildTemplateDefinition, wrapSelectionAsSections } from '../captureTemplate'
import { validateTemplateMetadata, isSupportedTemplateRoot } from '../templateValidation'
import TemplateSaveDialog from '../TemplateSaveDialog.vue'
import { localTemplateProvider } from '../../provider'
import { resolveTemplate } from '../../runtime'
import { useEditorStore } from '../../../stores/editor'
import { patternLibraryRegistry } from '../../../patterns/library'
import type { BlockNode, LayoutPayload, SectionNode } from '../../../types'

// A section carrying id-bearing children with token refs / responsive values, so we can
// assert ids are stripped while `data` is preserved verbatim.
function richSection(id = 'sec-1'): SectionNode {
  return {
    id,
    type: 'section',
    data: { background: '#111' },
    blocks: [
      { id: 'h1', type: 'heading', data: { text: 'Hi', color: '{{color.text}}', padding: { desktop: '2rem', mobile: '1rem' } } } as BlockNode,
    ],
  } as SectionNode
}

function containerNode(id = 'ctr-1'): BlockNode {
  return {
    id,
    type: 'container',
    data: { layout: 'flex', blocks: [{ id: 'col-1', type: 'column', data: { blocks: [{ id: 'b1', type: 'button', data: { label: 'Go' } }] } }] },
  } as BlockNode
}

/** Seed the editor payload with the given sections and select one by id (optional). */
function seedEditor(sections: SectionNode[], selectId: string | null = null) {
  const editor = useEditorStore()
  editor.currentPayload = { schema: 'pagebuilder/01', sections } as LayoutPayload
  if (selectId) editor.selectComponent(selectId)
  return editor
}

beforeEach(() => {
  setActivePinia(createPinia())
  localTemplateProvider.clear()
})

// ---------------------------------------------------------------------------
// Capture: section root
// ---------------------------------------------------------------------------
describe('capture — section root', () => {
  it('builds a tree-layout template from a section, stripping ids and keeping data', () => {
    const def = buildTemplateDefinition({ sections: [richSection()], name: ' Landing ', category: 'page' })

    expect(def.layout.kind).toBe('tree')
    const tree = (def.layout as { tree: { sections: SectionNode[] } }).tree
    expect(tree.sections).toHaveLength(1)

    const section = tree.sections[0] as unknown as Record<string, unknown>
    expect(section.id).toBeUndefined()
    expect((section.blocks as Array<Record<string, unknown>>).every((b) => b.id === undefined)).toBe(true)
    // Data preserved verbatim (token ref + responsive value survive).
    expect((tree.sections[0].blocks[0].data as { color: string }).color).toBe('{{color.text}}')
    expect((tree.sections[0].blocks[0].data as { padding: { mobile: string } }).padding.mobile).toBe('1rem')

    expect(def.name).toBe('Landing') // trimmed
    expect(def.category).toBe('page')
  })
})

// ---------------------------------------------------------------------------
// Capture: container root (wrapped) + page scope
// ---------------------------------------------------------------------------
describe('capture — container + page', () => {
  it('wraps a container selection in a synthetic section', () => {
    const sections = wrapSelectionAsSections(containerNode(), 'block')
    expect(sections).toHaveLength(1)
    expect(sections[0].type).toBe('section')
    expect(sections[0].blocks[0].type).toBe('container')

    const def = buildTemplateDefinition({ sections, name: 'Feature', category: 'landing' })
    const tree = (def.layout as { tree: { sections: SectionNode[] } }).tree
    expect(tree.sections[0].blocks[0].type).toBe('container')
    // Ids stripped through the whole nested tree.
    const container = tree.sections[0].blocks[0] as unknown as Record<string, unknown>
    expect(container.id).toBeUndefined()
  })

  it('captures the full page (multiple sections) via page scope', () => {
    seedEditor([richSection('a'), richSection('b')])
    const store = useTemplateAuthoringStore()

    expect(store.canSavePage).toBe(true)
    expect(store.openDialog('page')).toBe(true)
    store.setName('Whole Page')
    store.setCategory('page')
    expect(store.save()).toBe(true)

    const [tpl] = localTemplateProvider.all()
    const tree = (tpl.layout as { tree: { sections: SectionNode[] } }).tree
    expect(tree.sections).toHaveLength(2)
  })
})

// ---------------------------------------------------------------------------
// Rejection: invalid roots
// ---------------------------------------------------------------------------
describe('rejection — unsupported roots', () => {
  it('rejects a leaf block and a column as a template root', () => {
    expect(isSupportedTemplateRoot('button', 'block')).toBe(false)
    expect(isSupportedTemplateRoot('column', 'block')).toBe(false)
    // section and container are the only supported roots.
    expect(isSupportedTemplateRoot('section', 'section')).toBe(true)
    expect(isSupportedTemplateRoot('container', 'block')).toBe(true)
  })

  it('openDialog(selection) refuses when a leaf block is selected', () => {
    const section: SectionNode = { id: 's', type: 'section', data: {}, blocks: [{ id: 'btn', type: 'button', data: {} } as BlockNode] } as SectionNode
    seedEditor([section], 'btn')
    const store = useTemplateAuthoringStore()

    expect(store.canSaveSelection).toBe(false)
    expect(store.openDialog('selection')).toBe(false)
    expect(store.open).toBe(false)
    expect(store.errors.root).toBeTruthy()
    expect(localTemplateProvider.all()).toHaveLength(0)
  })

  it('openDialog(page) refuses an empty page', () => {
    seedEditor([])
    const store = useTemplateAuthoringStore()
    expect(store.canSavePage).toBe(false)
    expect(store.openDialog('page')).toBe(false)
    expect(store.open).toBe(false)
  })
})

// ---------------------------------------------------------------------------
// Source tree is never mutated
// ---------------------------------------------------------------------------
describe('source tree unchanged', () => {
  it('leaves the original section (and its ids) intact after capture', () => {
    const source = richSection('keep-me')
    buildTemplateDefinition({ sections: [source], name: 'X', category: 'page' })

    expect(source.id).toBe('keep-me')
    expect(source.blocks[0].id).toBe('h1')
    expect((source.blocks[0].data as { color: string }).color).toBe('{{color.text}}')
  })

  it('does not mutate the editor payload on save (metadata-only, no command/undo)', () => {
    const editor = seedEditor([richSection('sec-1')], 'sec-1')
    const before = JSON.stringify(editor.currentPayload)
    const store = useTemplateAuthoringStore()

    store.openDialog('selection')
    store.setName('Hero')
    store.setCategory('page')
    expect(store.save()).toBe(true)

    expect(JSON.stringify(editor.currentPayload)).toBe(before)
  })
})

// ---------------------------------------------------------------------------
// Metadata validation
// ---------------------------------------------------------------------------
describe('metadata validation', () => {
  it('requires a name and a known category', () => {
    expect(validateTemplateMetadata({ name: '', category: 'page' }).name).toBeTruthy()
    expect(validateTemplateMetadata({ name: 'ok', category: 'nope' }).category).toBeTruthy()
    expect(validateTemplateMetadata({ name: 'ok', category: 'page' })).toEqual({})
  })

  it('save() returns false with errors on invalid metadata and registers nothing', () => {
    seedEditor([richSection('sec-1')], 'sec-1')
    const store = useTemplateAuthoringStore()
    store.openDialog('selection')
    store.setName('') // invalid
    expect(store.save()).toBe(false)
    expect(store.errors.name).toBeTruthy()
    expect(localTemplateProvider.all()).toHaveLength(0)
  })
})

// ---------------------------------------------------------------------------
// Provider registration + runtime read-back
// ---------------------------------------------------------------------------
describe('provider registration + runtime', () => {
  it('registers a template with a unique id and never overwrites on repeat saves', () => {
    seedEditor([richSection('sec-1')], 'sec-1')
    const store = useTemplateAuthoringStore()

    store.openDialog('selection'); store.setName('One'); store.setCategory('page'); store.save()
    store.openDialog('selection'); store.setName('Two'); store.setCategory('page'); store.save()

    const all = localTemplateProvider.all()
    expect(all).toHaveLength(2)
    expect(all[0].id).not.toBe(all[1].id)
  })

  it('a saved template resolves through the Template Runtime into an independent tree', () => {
    seedEditor([richSection('sec-1')], 'sec-1')
    const store = useTemplateAuthoringStore()
    store.openDialog('selection'); store.setName('Hero'); store.setCategory('page')
    expect(store.save()).toBe(true)

    const [tpl] = localTemplateProvider.all()
    const resolved = resolveTemplate(tpl)
    expect(resolved.source).toBe('tree')
    expect(resolved.tree.sections).toHaveLength(1)
    expect(resolved.tree.sections[0].blocks[0].type).toBe('heading')
  })
})

// ---------------------------------------------------------------------------
// Pattern v1 untouched
// ---------------------------------------------------------------------------
describe('Pattern v1 unchanged', () => {
  it('template authoring does not register any patterns', () => {
    seedEditor([richSection('sec-1')], 'sec-1')
    const store = useTemplateAuthoringStore()
    store.openDialog('selection'); store.setName('Hero'); store.setCategory('page'); store.save()

    expect(patternLibraryRegistry.all()).toHaveLength(0)
  })
})

// ---------------------------------------------------------------------------
// Dialog
// ---------------------------------------------------------------------------
describe('TemplateSaveDialog', () => {
  it('renders when the store is open and reflects the capture scope', async () => {
    seedEditor([richSection('sec-1')])
    const store = useTemplateAuthoringStore()
    store.openDialog('page')

    const wrapper = mount(TemplateSaveDialog)
    await wrapper.vm.$nextTick()
    expect(wrapper.find('.pb-template-save').exists()).toBe(true)
    expect(wrapper.text()).toContain('the full page')
  })
})
