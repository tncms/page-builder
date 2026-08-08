import { describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import EditorNavigator from '../EditorNavigator.vue'
import { useEditorStore } from '../../../stores/editor'
import type { LayoutPayload, EditorMetadata } from '../../../types'

// sec1
//   ├ b1  (heading, leaf)
//   └ ctr (columns) ── btn (button, leaf)
// Flattened (all expanded): sec1, b1, ctr, btn
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

function mountTree() {
  const wrapper = mount(EditorNavigator)
  const tree = wrapper.find('[role="tree"]')
  return { wrapper, tree }
}

const active = (tree: ReturnType<typeof mountTree>['tree']) => tree.attributes('aria-activedescendant')

describe('EditorNavigator keyboard', () => {
  beforeEach(() => setup())

  it('exposes a focusable tree with treeitem levels and aria-expanded', () => {
    const { wrapper, tree } = mountTree()
    expect(tree.attributes('tabindex')).toBe('0')
    const items = wrapper.findAll('[role="treeitem"]')
    const sec = items.find((i) => i.attributes('id') === 'pb-nav-item-sec1')!
    const btn = items.find((i) => i.attributes('id') === 'pb-nav-item-btn')!
    expect(sec.attributes('aria-level')).toBe('1')
    expect(btn.attributes('aria-level')).toBe('3')
    // Container advertises expandability; leaf does not.
    expect(items.find((i) => i.attributes('id') === 'pb-nav-item-ctr')!.attributes('aria-expanded')).toBe('true')
    expect(btn.attributes('aria-expanded')).toBeUndefined()
  })

  it('initialises focus on tree focus and moves with Arrow/Home/End', async () => {
    const { tree } = mountTree()
    await tree.trigger('focus')
    expect(active(tree)).toBe('pb-nav-item-sec1')

    await tree.trigger('keydown', { key: 'ArrowDown' })
    expect(active(tree)).toBe('pb-nav-item-b1')
    await tree.trigger('keydown', { key: 'ArrowDown' })
    expect(active(tree)).toBe('pb-nav-item-ctr')
    await tree.trigger('keydown', { key: 'ArrowUp' })
    expect(active(tree)).toBe('pb-nav-item-b1')
    await tree.trigger('keydown', { key: 'End' })
    expect(active(tree)).toBe('pb-nav-item-btn')
    await tree.trigger('keydown', { key: 'Home' })
    expect(active(tree)).toBe('pb-nav-item-sec1')
  })

  it('ArrowRight enters an expanded container; ArrowLeft goes to the parent from a leaf', async () => {
    const { tree } = mountTree()
    await tree.trigger('focus')
    await tree.trigger('keydown', { key: 'End' }) // btn
    await tree.trigger('keydown', { key: 'ArrowLeft' })
    expect(active(tree)).toBe('pb-nav-item-ctr') // leaf → parent
    await tree.trigger('keydown', { key: 'ArrowRight' })
    expect(active(tree)).toBe('pb-nav-item-btn') // expanded container → first child
  })

  it('ArrowLeft collapses an expanded container in place', async () => {
    const store = useEditorStore()
    const { tree } = mountTree()
    await tree.trigger('focus')
    await tree.trigger('keydown', { key: 'ArrowDown' })
    await tree.trigger('keydown', { key: 'ArrowDown' }) // ctr
    expect(store.isCollapsed('ctr')).toBe(false)
    await tree.trigger('keydown', { key: 'ArrowLeft' })
    expect(store.isCollapsed('ctr')).toBe(true)
    expect(active(tree)).toBe('pb-nav-item-ctr') // focus stays
  })

  it('Enter commits focus to selection without conflating the two', async () => {
    const store = useEditorStore()
    const { tree } = mountTree()
    await tree.trigger('focus')
    await tree.trigger('keydown', { key: 'ArrowDown' }) // focus b1
    expect(store.selectedComponentId).not.toBe('b1')
    await tree.trigger('keydown', { key: 'Enter' })
    expect(store.selectedComponentId).toBe('b1')
    expect(active(tree)).toBe('pb-nav-item-b1')
  })

  it('Escape re-anchors focus to the selected node', async () => {
    const store = useEditorStore()
    const { tree } = mountTree()
    store.selectComponent('ctr')
    await tree.trigger('focus')
    await tree.trigger('keydown', { key: 'End' }) // focus btn (≠ selection)
    expect(active(tree)).toBe('pb-nav-item-btn')
    await tree.trigger('keydown', { key: 'Escape' })
    expect(active(tree)).toBe('pb-nav-item-ctr')
  })

  it('Shift+F10 opens the focused row context menu', async () => {
    const { wrapper, tree } = mountTree()
    await tree.trigger('focus')
    await tree.trigger('keydown', { key: 'ArrowDown' }) // focus b1
    expect(wrapper.find('.pb-editor-navigator-menu').exists()).toBe(false)

    await tree.trigger('keydown', { key: 'F10', shiftKey: true })
    await nextTick()
    expect(wrapper.find('.pb-editor-navigator-menu').exists()).toBe(true)
  })

  it('restores focus to the next sibling when the focused row disappears', async () => {
    const store = useEditorStore()
    const { tree } = mountTree()
    await tree.trigger('focus')
    await tree.trigger('keydown', { key: 'ArrowDown' }) // focus b1
    expect(active(tree)).toBe('pb-nav-item-b1')

    // Remove b1 from the tree (as a structural command would) → fall to next sibling ctr.
    store.currentPayload = {
      version: 1,
      sections: [
        { id: 'sec1', type: 'hero', data: {}, blocks: [{ id: 'ctr', type: 'columns', data: { blocks: [{ id: 'btn', type: 'button', data: {} }] } }] },
      ],
    } as unknown as LayoutPayload
    await nextTick()
    expect(active(tree)).toBe('pb-nav-item-ctr')
  })
})
