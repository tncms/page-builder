import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { mount, flushPromises } from '@vue/test-utils'
import { computed } from 'vue'
import NavigatorNode from '../NavigatorNode.vue'
import { NavigatorLocksKey } from '../injection'
import { useEditorStore } from '../../../stores/editor'
import { useNavigatorLocksStore } from '../../../stores/navigatorLocks'
import type { ComponentNode, LayoutPayload } from '../../../types'

// Move Up/Down contract (Increment 4.2, Step 5). Each move is one guarded
// `move-component` dispatch reordering among siblings only; the mutator
// removes-then-inserts, so within a parent `index ± 1` is the exact target.
// Boundaries disable the item; locked nodes are blocked at the single store guard.

function node(id: string, over: Partial<ComponentNode> = {}): ComponentNode {
  return { id, type: 'heading', kind: 'block', parentId: 'sec', depth: 1, disabled: false, data: {}, children: [], ...over }
}

function setup(payload: LayoutPayload) {
  setActivePinia(createPinia())
  const store = useEditorStore()
  store.currentPayload = payload
  return store
}

function mountNode(store: ReturnType<typeof setup>, n: ComponentNode) {
  const spy = vi.spyOn(store, 'executeCommand').mockResolvedValue(true)
  const wrapper = mount(NavigatorNode, { props: { node: n } })
  return { wrapper, spy }
}

const item = (w: ReturnType<typeof mount>, label: string) =>
  w.findAll('button[role="menuitem"]').find((b) => b.text() === label)!

async function openMenu(w: ReturnType<typeof mount>) {
  await w.find('.pb-editor-navigator-menu-btn').trigger('click')
}

const blocks = () =>
  ({
    version: 1,
    sections: [
      {
        id: 'sec',
        type: 'hero',
        data: {},
        blocks: [
          { id: 'b1', type: 'heading', data: {} },
          { id: 'b2', type: 'heading', data: {} },
          { id: 'b3', type: 'heading', data: {} },
        ],
      },
    ],
  }) as unknown as LayoutPayload

describe('Navigator Move Up/Down contract (Increment 4.2)', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('Move Up is disabled at the first-sibling boundary', async () => {
    const store = setup(blocks())
    const { wrapper } = mountNode(store, node('b1'))
    await openMenu(wrapper)
    expect(item(wrapper, 'Move Up').attributes('disabled')).toBeDefined()
    expect(item(wrapper, 'Move Down').attributes('disabled')).toBeUndefined()
  })

  it('Move Down is disabled at the last-sibling boundary', async () => {
    const store = setup(blocks())
    const { wrapper } = mountNode(store, node('b3'))
    await openMenu(wrapper)
    expect(item(wrapper, 'Move Down').attributes('disabled')).toBeDefined()
    expect(item(wrapper, 'Move Up').attributes('disabled')).toBeUndefined()
  })

  it('reorders sections at the root (target_parent_id = null)', async () => {
    const store = setup({
      version: 1,
      sections: [
        { id: 'sec1', type: 'hero', data: {}, blocks: [] },
        { id: 'sec2', type: 'hero', data: {}, blocks: [] },
        { id: 'sec3', type: 'hero', data: {}, blocks: [] },
      ],
    } as unknown as LayoutPayload)
    const { wrapper, spy } = mountNode(store, node('sec2', { type: 'hero', kind: 'section', parentId: null, depth: 0 }))
    await openMenu(wrapper)
    await item(wrapper, 'Move Down').trigger('click')
    await flushPromises()
    expect(spy).toHaveBeenCalledTimes(1)
    expect(spy).toHaveBeenCalledWith('move-component', { component_id: 'sec2', target_parent_id: null, index: 2 })
  })

  it('reorders column siblings inside their container', async () => {
    const store = setup({
      version: 1,
      sections: [
        {
          id: 'sec',
          type: 'hero',
          data: {},
          blocks: [
            {
              id: 'ctr',
              type: 'columns',
              data: { blocks: [{ id: 'c1', type: 'column', data: {} }, { id: 'c2', type: 'column', data: {} }] },
            },
          ],
        },
      ],
    } as unknown as LayoutPayload)
    const { wrapper, spy } = mountNode(store, node('c1', { type: 'column', parentId: 'ctr', depth: 2 }))
    await openMenu(wrapper)
    await item(wrapper, 'Move Down').trigger('click')
    await flushPromises()
    expect(spy).toHaveBeenCalledTimes(1)
    expect(spy).toHaveBeenCalledWith('move-component', { component_id: 'c1', target_parent_id: 'ctr', index: 1 })
  })

  it('reorders element siblings inside their container', async () => {
    const store = setup({
      version: 1,
      sections: [
        {
          id: 'sec',
          type: 'hero',
          data: {},
          blocks: [
            {
              id: 'cont',
              type: 'container',
              data: { blocks: [{ id: 'e1', type: 'heading', data: {} }, { id: 'e2', type: 'heading', data: {} }, { id: 'e3', type: 'heading', data: {} }] },
            },
          ],
        },
      ],
    } as unknown as LayoutPayload)
    const { wrapper, spy } = mountNode(store, node('e2', { parentId: 'cont', depth: 2 }))
    await openMenu(wrapper)
    await item(wrapper, 'Move Up').trigger('click')
    await flushPromises()
    expect(spy).toHaveBeenCalledTimes(1)
    expect(spy).toHaveBeenCalledWith('move-component', { component_id: 'e2', target_parent_id: 'cont', index: 0 })
  })

  it('a locked node blocks Move (item disabled AND no dispatch)', async () => {
    const store = setup(blocks())
    const locks = useNavigatorLocksStore()
    locks.lock(store.owner, 'b2')
    const spy = vi.spyOn(store, 'executeCommand').mockResolvedValue(true)
    // The tree root projects the SAME lock store into each row; provide that
    // projection so the menu's disabled state reflects the lock.
    const wrapper = mount(NavigatorNode, {
      props: { node: node('b2') },
      global: { provide: { [NavigatorLocksKey as symbol]: computed(() => locks.lockedSet(store.owner)) } },
    })
    await openMenu(wrapper)
    expect(item(wrapper, 'Move Up').attributes('disabled')).toBeDefined()
    expect(item(wrapper, 'Move Down').attributes('disabled')).toBeDefined()
    // Even a direct store move is a no-op for a locked node.
    await store.moveComponent('b2', 'sec', 0)
    expect(spy).not.toHaveBeenCalled()
  })

  it('a failed command dispatches exactly once and leaves the payload unchanged', async () => {
    const store = setup(blocks())
    const before = JSON.parse(JSON.stringify(store.currentPayload))
    const spy = vi.spyOn(store, 'executeCommand').mockResolvedValue(false) // command rejected / no change
    const wrapper = mount(NavigatorNode, { props: { node: node('b1') } })
    await openMenu(wrapper)
    await item(wrapper, 'Move Down').trigger('click')
    await flushPromises()
    expect(spy).toHaveBeenCalledTimes(1)
    expect(store.currentPayload).toEqual(before)
  })
})
