import { describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { mount } from '@vue/test-utils'
import EditorNavigator from '../EditorNavigator.vue'
import EditorBreadcrumb from '../EditorBreadcrumb.vue'
import { useEditorStore } from '../../../stores/editor'
import type { LayoutPayload, EditorMetadata } from '../../../types'

function payload(): LayoutPayload {
  return {
    version: 1,
    sections: [
      {
        id: 'sec1',
        type: 'hero',
        data: {},
        blocks: [
          { id: 'b1', type: 'heading', data: {} },
          { id: 'ctr', type: 'columns', data: { blocks: [{ id: 'btn', type: 'button', data: {} }] } },
        ],
      },
    ],
  } as unknown as LayoutPayload
}

const metadata = {
  version: '1',
  sections: [{ type: 'hero', label: 'Hero' }],
  blocks: [
    { type: 'heading', label: 'Heading' },
    { type: 'columns', label: 'Columns' },
    { type: 'button', label: 'Button' },
  ],
  tokens: {},
} as unknown as EditorMetadata

function setup() {
  setActivePinia(createPinia())
  const store = useEditorStore()
  store.currentPayload = payload()
  store.metadata = metadata
  return store
}

describe('EditorNavigator', () => {
  beforeEach(() => setup())

  it('renders the whole tree with metadata labels', () => {
    const wrapper = mount(EditorNavigator)
    const text = wrapper.text()
    expect(text).toContain('Hero')
    expect(text).toContain('Columns')
    expect(text).toContain('Button')
  })

  it('shows container + column with width and child count (Phase 25.3.1 §10)', () => {
    const store = useEditorStore()
    store.currentPayload = {
      version: 1,
      sections: [
        {
          id: 'sec1',
          type: 'hero',
          data: {},
          blocks: [
            {
              id: 'con1',
              type: 'container',
              data: {
                blocks: [
                  { id: 'colA', type: 'column', data: { width: '50', blocks: [{ id: 'btn', type: 'button', data: {} }] } },
                  { id: 'colB', type: 'column', data: { width: '50', blocks: [] } },
                ],
              },
            },
          ],
        },
      ],
    } as unknown as LayoutPayload
    store.metadata = {
      version: '1',
      sections: [{ type: 'hero', label: 'Hero' }],
      blocks: [
        { type: 'container', label: 'Container' },
        { type: 'column', label: 'Column' },
        { type: 'button', label: 'Button' },
      ],
      tokens: {},
    } as unknown as EditorMetadata

    const text = mount(EditorNavigator).text()
    expect(text).toContain('Container')
    expect(text).toContain('Column')
    expect(text).toContain('50%')
    expect(text).toContain('1 item')
    expect(text).toContain('empty')
  })

  it('shows an empty state for an empty layout', () => {
    const store = useEditorStore()
    store.currentPayload = { version: 1, sections: [] } as unknown as LayoutPayload
    const wrapper = mount(EditorNavigator)
    expect(wrapper.find('.pb-editor-navigator__empty').exists()).toBe(true)
    expect(wrapper.find('.pb-editor-navigator__tree').exists()).toBe(false)
  })

  it('selects a component on row click (navigator → store)', async () => {
    const store = useEditorStore()
    const wrapper = mount(EditorNavigator)
    const rows = wrapper.findAll('.pb-editor-navigator-row')
    await rows[0].trigger('click')
    expect(store.selectedComponentId).toBe('sec1')
  })

  it('reflects the selected component (store → navigator)', () => {
    const store = useEditorStore()
    store.selectComponent('ctr')
    const wrapper = mount(EditorNavigator)
    const selected = wrapper.find('.pb-editor-navigator-row.is-selected')
    expect(selected.exists()).toBe(true)
    expect(selected.text()).toContain('Columns')
  })

  it('mirrors hover into the canvas hover state', async () => {
    const store = useEditorStore()
    const wrapper = mount(EditorNavigator)
    await wrapper.findAll('.pb-editor-navigator-row')[0].trigger('mouseenter')
    expect(store.hoveredComponentId).toBe('sec1')
  })

  it('filters the tree by query and prunes non-matches', async () => {
    const wrapper = mount(EditorNavigator)
    await wrapper.find('.pb-editor-navigator__input').setValue('button')
    const text = wrapper.text()
    expect(text).toContain('Button')
    expect(text).not.toContain('Heading')
  })

  it('shows a no-match empty state', async () => {
    const wrapper = mount(EditorNavigator)
    await wrapper.find('.pb-editor-navigator__input').setValue('zzz-nothing')
    expect(wrapper.find('.pb-editor-navigator__empty').exists()).toBe(true)
  })
})

describe('EditorBreadcrumb', () => {
  beforeEach(() => setup())

  it('renders nothing when no component is selected', () => {
    expect(mount(EditorBreadcrumb).find('.pb-editor-breadcrumb').exists()).toBe(false)
  })

  it('renders the selection path as chips', () => {
    const store = useEditorStore()
    store.selectComponent('btn')
    const chips = mount(EditorBreadcrumb).findAll('.pb-editor-breadcrumb__chip')
    expect(chips.map((c) => c.text())).toEqual(['Hero', 'Columns', 'Button'])
    expect(chips[2].classes()).toContain('is-current')
  })

  it('selects an ancestor when its chip is clicked', async () => {
    const store = useEditorStore()
    store.selectComponent('btn')
    const wrapper = mount(EditorBreadcrumb)
    await wrapper.findAll('.pb-editor-breadcrumb__chip')[0].trigger('click')
    expect(store.selectedComponentId).toBe('sec1')
  })
})
