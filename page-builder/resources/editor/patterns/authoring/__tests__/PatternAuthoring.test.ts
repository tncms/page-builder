// Phase 26.13 — Pattern Authoring & Save Flow.
//
// Covers serialization (tree captured, ids removed, source untouched, tokens/responsive/
// state styles preserved), root wrapping, metadata validation, the save flow (creation,
// registration, duplicate protection, picker visibility, feedback), and the regression
// contract (no command, no undo entry, editor state untouched; insert still works).

import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'

import { usePatternAuthoringStore } from '../patternAuthoringStore'
import { useEditorStore } from '../../../stores/editor'
import { usePatternPickerStore } from '../../picker/patternPickerStore'
import { patternLibraryRegistry, insertPattern } from '../../library'
import { buildPatternDefinition, wrapRootAsSections } from '../savePattern'
import { validatePatternMetadata, isValidPatternMetadata, isSupportedRoot } from '../patternValidation'
import PatternSaveDialog from '../PatternSaveDialog.vue'
import type { BlockNode, LayoutPayload, SectionNode } from '../../../types'

// A section with every capability that must survive, plus a leaf button (for the
// unsupported-root case) and layout blocks used by the wrapping tests.
function richSection(): SectionNode {
  return {
    id: 'sec-1',
    type: 'section',
    data: {
      background: '#ffffff', // normal value
      padding: { desktop: '{{spacing.lg}}', mobile: '{{spacing.sm}}' }, // responsive + token
      states: { hover: { background: '{{color.primary}}' } }, // 26.10 style-state map
    },
    blocks: [
      { id: 'h1', type: 'heading', data: { text: 'Hi', color: '{{color.text}}' } },
      { id: 'btn-1', type: 'button', data: { label: 'Go' } },
    ],
  }
}

function containerNode(): BlockNode {
  return { id: 'ctr-1', type: 'container', data: { layout: 'flex', blocks: [{ id: 'x', type: 'text', data: {} }] } }
}
function columnNode(): BlockNode {
  return { id: 'col-1', type: 'column', data: { width: '50', blocks: [{ id: 'y', type: 'text', data: {} }] } }
}

function seedEditor(selectId = 'sec-1') {
  const editor = useEditorStore()
  editor.currentPayload = { schema: 'pagebuilder/01', sections: [richSection()] } as LayoutPayload
  editor.selectComponent(selectId)
  return editor
}

beforeEach(() => {
  setActivePinia(createPinia())
  patternLibraryRegistry.clear()
})

// ---- Serialization (via the 26.11 serializer) --------------------------------

describe('buildPatternDefinition — serialization', () => {
  const meta = { name: 'Hero', category: 'hero' }

  it('captures the tree, wrapping-free for a section', () => {
    const def = buildPatternDefinition({ node: richSection(), kind: 'section', ...meta })
    expect(def.tree.sections).toHaveLength(1)
    expect(def.tree.sections[0].type).toBe('section')
    expect(def.tree.sections[0].blocks.map((b) => b.type)).toEqual(['heading', 'button'])
  })

  it('removes component ids at every level', () => {
    const def = buildPatternDefinition({ node: richSection(), kind: 'section', ...meta })
    const section = def.tree.sections[0]
    expect('id' in section).toBe(false)
    expect(section.blocks.every((b) => !('id' in b))).toBe(true)
  })

  it('does not mutate the source tree', () => {
    const source = richSection()
    buildPatternDefinition({ node: source, kind: 'section', ...meta })
    expect(source.id).toBe('sec-1')
    expect(source.blocks[0].id).toBe('h1')
  })

  it('preserves tokens, responsive values and state styles', () => {
    const def = buildPatternDefinition({ node: richSection(), kind: 'section', ...meta })
    const data = def.tree.sections[0].data
    expect(data.background).toBe('#ffffff')
    expect(data.padding).toEqual({ desktop: '{{spacing.lg}}', mobile: '{{spacing.sm}}' })
    expect(data.states).toEqual({ hover: { background: '{{color.primary}}' } })
    expect(def.tree.sections[0].blocks[0].data.color).toBe('{{color.text}}')
  })
})

// ---- Root wrapping -----------------------------------------------------------

describe('wrapRootAsSections', () => {
  it('uses a section as-is', () => {
    const [s] = wrapRootAsSections(richSection(), 'section')
    expect(s.type).toBe('section')
  })

  it('wraps a container in a section', () => {
    const [s] = wrapRootAsSections(containerNode(), 'block')
    expect(s.type).toBe('section')
    expect(s.blocks[0].type).toBe('container')
  })

  it('wraps a column in a section > container (valid nesting)', () => {
    const [s] = wrapRootAsSections(columnNode(), 'block')
    expect(s.type).toBe('section')
    expect(s.blocks[0].type).toBe('container')
    expect((s.blocks[0].data.blocks as BlockNode[])[0].type).toBe('column')
  })

  it('gates saveable roots: sections + container/column only', () => {
    expect(isSupportedRoot('section', 'section')).toBe(true)
    expect(isSupportedRoot('hero', 'section')).toBe(true)
    expect(isSupportedRoot('container', 'block')).toBe(true)
    expect(isSupportedRoot('column', 'block')).toBe(true)
    expect(isSupportedRoot('button', 'block')).toBe(false)
    expect(isSupportedRoot('text', 'block')).toBe(false)
  })
})

// ---- Metadata validation -----------------------------------------------------

describe('validatePatternMetadata', () => {
  it('requires a name', () => {
    expect(validatePatternMetadata({ name: '', category: 'hero' }).name).toBe('Name is required.')
    expect(validatePatternMetadata({ name: '   ', category: 'hero' }).name).toBeTruthy()
  })
  it('rejects an over-long name', () => {
    expect(validatePatternMetadata({ name: 'x'.repeat(81), category: 'hero' }).name).toBeTruthy()
  })
  it('requires a known category', () => {
    expect(validatePatternMetadata({ name: 'X', category: 'nope' }).category).toBe('Choose a category.')
    expect(validatePatternMetadata({ name: 'X', category: 'hero' }).category).toBeUndefined()
  })
  it('accepts valid metadata', () => {
    expect(isValidPatternMetadata({ name: 'Hero A', category: 'hero', description: 'x' })).toBe(true)
  })
})

// ---- Save flow (store) -------------------------------------------------------

describe('patternAuthoringStore', () => {
  it('opens only for a supported selection', () => {
    seedEditor('sec-1')
    const store = usePatternAuthoringStore()
    expect(store.canSaveSelection).toBe(true)
    expect(store.openDialog()).toBe(true)
    expect(store.open).toBe(true)
  })

  it('refuses to open for a leaf block', () => {
    seedEditor('btn-1')
    const store = usePatternAuthoringStore()
    expect(store.canSaveSelection).toBe(false)
    expect(store.openDialog()).toBe(false)
    expect(store.open).toBe(false)
    expect(store.errors.root).toBeTruthy()
  })

  it('validates on save and does not register invalid input', () => {
    seedEditor('sec-1')
    const store = usePatternAuthoringStore()
    store.openDialog()
    // name left blank
    expect(store.save()).toBe(false)
    expect(store.errors.name).toBeTruthy()
    expect(patternLibraryRegistry.all()).toHaveLength(0)
  })

  it('creates + registers a pattern and shows feedback', () => {
    seedEditor('sec-1')
    const store = usePatternAuthoringStore()
    store.openDialog()
    store.setName('Hero Landing')
    store.setCategory('hero')
    store.setDescription('A hero')
    expect(store.save()).toBe(true)

    const all = patternLibraryRegistry.all()
    expect(all).toHaveLength(1)
    expect(all[0].name).toBe('Hero Landing')
    expect(all[0].category).toBe('hero')
    expect(store.feedback).toBe('Pattern "Hero Landing" created.')
    expect(store.open).toBe(false)
  })

  it('generates unique ids — repeated saves never overwrite', () => {
    seedEditor('sec-1')
    const store = usePatternAuthoringStore()
    store.openDialog(); store.setName('Hero'); store.setCategory('hero'); store.save()
    store.openDialog(); store.setName('Hero'); store.setCategory('hero'); store.save()
    const all = patternLibraryRegistry.all()
    expect(all).toHaveLength(2)
    expect(all[0].id).not.toBe(all[1].id)
  })

  it('makes the pattern appear in the picker immediately', () => {
    seedEditor('sec-1')
    const picker = usePatternPickerStore()
    picker.openPicker()
    expect(picker.results).toHaveLength(0)

    const store = usePatternAuthoringStore()
    store.openDialog(); store.setName('Hero'); store.setCategory('hero'); store.save()

    expect(picker.results).toHaveLength(1) // refreshed without a manual reload
    expect(picker.results[0].name).toBe('Hero')
  })
})

// ---- Regression: no command, no undo, editor untouched -----------------------

describe('saving is metadata-only', () => {
  it('creates no undo entry and does not mutate the payload', () => {
    const editor = seedEditor('sec-1')
    const before = JSON.stringify(editor.currentPayload)
    const runCommand = vi.fn()
    ;(editor as unknown as { client: unknown }).client = { runCommand }

    const store = usePatternAuthoringStore()
    store.openDialog(); store.setName('Hero'); store.setCategory('hero'); store.save()

    expect(runCommand).not.toHaveBeenCalled() // no command dispatched
    expect(editor.undoStack).toHaveLength(0) // no undo entry
    expect(JSON.stringify(editor.currentPayload)).toBe(before) // payload unchanged
    expect(editor.selectedComponentId).toBe('sec-1') // selection unchanged
  })

  it('the authored pattern still inserts through the existing pipeline (one command)', async () => {
    const editor = seedEditor('sec-1')
    let n = 0
    const runCommand = vi.fn(async (b: LayoutPayload, command: string, params: Record<string, unknown>) => {
      if (command !== 'insert-pattern') return { payload: b, selection: { selected: null } }
      const added = (params.sections as SectionNode[]).map((s) => ({ ...s, id: `srv-${(n += 1)}` }))
      return { payload: { ...b, sections: [...b.sections, ...added] }, selection: { selected: added[0].id } }
    })
    ;(editor as unknown as { client: unknown }).client = { runCommand }

    const store = usePatternAuthoringStore()
    store.openDialog(); store.setName('Hero'); store.setCategory('hero'); store.save()
    const def = patternLibraryRegistry.all()[0]

    const ok = await insertPattern(def, editor)
    expect(ok).toBe(true)
    expect(runCommand).toHaveBeenCalledTimes(1)
    expect(runCommand.mock.calls[0][1]).toBe('insert-pattern')
  })
})

// ---- Dialog component --------------------------------------------------------

describe('PatternSaveDialog', () => {
  it('renders the metadata form when open and lists the fixed categories', async () => {
    seedEditor('sec-1')
    const store = usePatternAuthoringStore()
    store.openDialog()
    const wrapper = mount(PatternSaveDialog)
    await nextTick()

    expect(wrapper.find('.pb-pattern-save__panel').exists()).toBe(true)
    expect(wrapper.findAll('option').map((o) => o.text())).toEqual(['hero', 'content', 'cta', 'gallery', 'layout'])
  })

  it('shows a validation error when submitting without a name', async () => {
    seedEditor('sec-1')
    usePatternAuthoringStore().openDialog()
    const wrapper = mount(PatternSaveDialog)
    await wrapper.find('form').trigger('submit')
    expect(wrapper.find('.pb-pattern-save__error').text()).toBe('Name is required.')
    expect(patternLibraryRegistry.all()).toHaveLength(0)
  })

  it('creates the pattern and closes on a valid submit', async () => {
    seedEditor('sec-1')
    const store = usePatternAuthoringStore()
    store.openDialog()
    const wrapper = mount(PatternSaveDialog)
    await wrapper.find('.pb-pattern-save__input').setValue('Hero Landing')
    await wrapper.find('form').trigger('submit')

    expect(patternLibraryRegistry.all()).toHaveLength(1)
    expect(store.open).toBe(false)
    await nextTick()
    expect(wrapper.find('.pb-pattern-save__panel').exists()).toBe(false)
  })
})
