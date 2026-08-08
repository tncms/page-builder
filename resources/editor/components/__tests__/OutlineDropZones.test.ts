import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { mount } from '@vue/test-utils'
import CanvasNodeTree from '../CanvasNodeTree.vue'
import { useEditorStore } from '../../stores/editor'
import type { LayoutPayload, EditorMetadata } from '../../types'

// A section with a leaf block + an empty container block (data.blocks: []).
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
          { id: 'ctr', type: 'columns', data: { blocks: [] } },
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

describe('Outline drop zones (Phase 14.4)', () => {
  beforeEach(() => setup())

  it('renders a root gap zone before and after each top-level section', () => {
    const store = useEditorStore()
    const wrapper = mount(CanvasNodeTree, { props: { nodes: store.nodeTree, parentId: null } })
    // Root list has 1 section → 2 gap zones (index 0 and 1), both parent="" (root).
    const rootZones = wrapper
      .findAll('[data-pb-drop-zone]')
      .filter((z) => z.attributes('data-pb-drop-parent') === '' && z.attributes('data-pb-drop-kind') === 'gap')
    expect(rootZones.map((z) => z.attributes('data-pb-drop-index'))).toEqual(['0', '1'])
  })

  it('exposes an inside zone for the empty container block', () => {
    const store = useEditorStore()
    const wrapper = mount(CanvasNodeTree, { props: { nodes: store.nodeTree, parentId: null } })
    const inside = wrapper
      .findAll('[data-pb-drop-zone]')
      .filter((z) => z.attributes('data-pb-drop-kind') === 'inside')
    // The empty 'columns' container exposes an inside zone bound to its own id.
    expect(inside.some((z) => z.attributes('data-pb-drop-parent') === 'ctr')).toBe(true)
  })

  it('interleaves block gap zones inside a section, parented to that section', () => {
    const store = useEditorStore()
    const wrapper = mount(CanvasNodeTree, { props: { nodes: store.nodeTree, parentId: null } })
    const sec1Zones = wrapper
      .findAll('[data-pb-drop-zone]')
      .filter((z) => z.attributes('data-pb-drop-parent') === 'sec1' && z.attributes('data-pb-drop-kind') === 'gap')
    // sec1 has 2 children → 3 gap zones (0,1,2).
    expect(sec1Zones.map((z) => z.attributes('data-pb-drop-index'))).toEqual(['0', '1', '2'])
  })

  it('starts an outline drag from a card handle (pointerdown → store)', async () => {
    const store = useEditorStore()
    const spy = vi.spyOn(store, 'beginOutlineDrag')
    const wrapper = mount(CanvasNodeTree, { props: { nodes: store.nodeTree, parentId: null } })
    const handle = wrapper.find('.pb-editor-node-handle')
    expect(handle.exists()).toBe(true)
    await handle.trigger('pointerdown')
    expect(spy).toHaveBeenCalledOnce()
    expect(spy.mock.calls[0][0].id).toBe('sec1') // dragging the section card
  })
})
