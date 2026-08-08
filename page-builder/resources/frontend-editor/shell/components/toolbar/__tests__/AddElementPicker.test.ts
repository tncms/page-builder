// Phase 26.3.10 — AddElementPicker renders the nesting-policy-filtered component
// candidates for the selected container and commits a pick through the single
// guarded insertion entry point (toolbar.chooseAddElement → insertComponent).
import { describe, it, expect, beforeEach, vi, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import AddElementPicker from '../AddElementPicker.vue'
import { useToolbarStore } from '../../../../toolbar/ToolbarManager'
import { useEditorStore } from '../../../../../editor/stores/editor'
import type { EditorMetadata, LayoutPayload } from '../../../../../editor/types'

// A columns container 'c1' holding one empty column 'col1'.
function payload(): LayoutPayload {
  return {
    schema: 'pagebuilder/01',
    sections: [
      {
        id: 's1',
        type: 'section',
        data: {},
        blocks: [
          {
            id: 'c1',
            type: 'container',
            data: { blocks: [{ id: 'col1', type: 'column', data: { width: '100', blocks: [] } }] },
          },
        ],
      },
    ],
  } as unknown as LayoutPayload
}

const METADATA = {
  version: '1',
  sections: [],
  blocks: [
    { type: 'text', label: 'Text' },
    { type: 'button', label: 'Button' },
    { type: 'image', label: 'Image' },
    { type: 'icon', label: 'Icon' },
    { type: 'column', label: 'Column' }, // must be filtered out (column can't hold a column)
  ],
  tokens: {},
} as unknown as EditorMetadata

function seed(selected: string) {
  const editor = useEditorStore()
  editor.currentPayload = payload()
  editor.metadata = METADATA
  editor.selectComponent(selected)
  const toolbar = useToolbarStore()
  toolbar.addElementPickerOpen = true
  return { editor, toolbar }
}

describe('AddElementPicker (Phase 26.3.10)', () => {
  beforeEach(() => setActivePinia(createPinia()))
  afterEach(() => vi.restoreAllMocks())

  it('renders only the nesting-policy-valid content blocks for the selected column', () => {
    seed('col1')
    const wrapper = mount(AddElementPicker)
    const items = wrapper.findAll('.pb-editor-navigator-picker__item')
    // All content blocks (text/button/image/icon), but NOT column — a column
    // cannot be nested inside a column (nesting policy).
    expect(items.length).toBe(4)
    for (const label of ['Text', 'Button', 'Image', 'Icon']) {
      expect(wrapper.text()).toContain(label)
    }
    expect(wrapper.text()).not.toContain('Column')
  })

  it('renders nothing while closed', () => {
    const { toolbar } = seed('col1')
    toolbar.addElementPickerOpen = false
    const wrapper = mount(AddElementPicker)
    expect(wrapper.find('.pb-editor-navigator-picker__item').exists()).toBe(false)
  })

  it('picking a component inserts it via the guarded insertComponent() path and closes', async () => {
    const { editor, toolbar } = seed('col1')
    const spy = vi.spyOn(editor, 'insertComponent').mockResolvedValue('new-id')
    const wrapper = mount(AddElementPicker)
    await wrapper.findAll('.pb-editor-navigator-picker__item')[0].trigger('click')
    expect(spy).toHaveBeenCalledWith({ parentId: 'col1', componentType: 'text' })
    expect(toolbar.addElementPickerOpen).toBe(false)
  })
})
