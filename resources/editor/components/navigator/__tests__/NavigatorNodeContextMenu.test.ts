import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { mount } from '@vue/test-utils'
import { computed } from 'vue'
import NavigatorNode from '../NavigatorNode.vue'
import { NavigatorLocksKey } from '../injection'
import { useEditorStore } from '../../../stores/editor'
import { useNavigatorLocksStore } from '../../../stores/navigatorLocks'
import type { ComponentNode } from '../../../types'

function container(): ComponentNode {
  return {
    id: 'ctr', type: 'container', kind: 'block', parentId: 'sec', depth: 1, disabled: false,
    data: { blocks: [] },
    children: [
      { id: 'child', type: 'button', kind: 'block', parentId: 'ctr', depth: 2, disabled: false, data: {}, children: [] },
    ],
  }
}

async function openMenu(locked = false) {
  const wrapper = mount(NavigatorNode, {
    props: { node: container() },
    global: locked
      ? { provide: { [NavigatorLocksKey as symbol]: computed(() => new Set(['ctr'])) } }
      : {},
  })
  await wrapper.find('.pb-editor-navigator-menu-btn').trigger('click')
  return wrapper
}

const item = (wrapper: Awaited<ReturnType<typeof openMenu>>, label: string) =>
  wrapper.findAll('[role="menuitem"]').find((b) => b.text() === label)!

describe('NavigatorNode context menu v2', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('Lock toggles the owner-scoped session lock store', async () => {
    const store = useEditorStore()
    const locks = useNavigatorLocksStore()
    const wrapper = await openMenu()

    expect(locks.isLocked(store.owner, 'ctr')).toBe(false)
    await item(wrapper, 'Lock').trigger('click')
    expect(locks.isLocked(store.owner, 'ctr')).toBe(true)
  })

  it('Copy ID writes the raw node id to the clipboard', async () => {
    const writeText = vi.fn().mockResolvedValue(undefined)
    Object.defineProperty(navigator, 'clipboard', { value: { writeText }, configurable: true })
    const wrapper = await openMenu()

    await item(wrapper, 'Copy ID').trigger('click')
    expect(writeText).toHaveBeenCalledWith('ctr')
  })

  it('a locked node disables removal/structural items but keeps Copy and Unlock', async () => {
    const wrapper = await openMenu(true)

    expect(item(wrapper, 'Cut').attributes('disabled')).toBeDefined()
    expect(item(wrapper, 'Paste inside').attributes('disabled')).toBeDefined()
    expect(item(wrapper, 'Delete').attributes('disabled')).toBeDefined()
    // Non-destructive + the unlock path stay available.
    expect(item(wrapper, 'Copy').attributes('disabled')).toBeUndefined()
    expect(item(wrapper, 'Copy ID').attributes('disabled')).toBeUndefined()
    expect(item(wrapper, 'Unlock')).toBeTruthy()
  })
})

function block(id: string): ComponentNode {
  return { id, type: 'heading', kind: 'block', parentId: 'sec', depth: 1, disabled: false, data: {}, children: [] }
}

// Store tree: sec → [b1, b2, b3]; mount the NavigatorNode for `id`.
async function mountWithSiblings(id: string) {
  const store = useEditorStore()
  store.currentPayload = {
    version: 1,
    sections: [{ id: 'sec', type: 'hero', data: {}, blocks: [{ id: 'b1', type: 'heading', data: {} }, { id: 'b2', type: 'heading', data: {} }, { id: 'b3', type: 'heading', data: {} }] }],
  } as never
  const spy = vi.spyOn(store, 'executeCommand').mockResolvedValue(true)
  const wrapper = mount(NavigatorNode, { props: { node: block(id) } })
  await wrapper.find('.pb-editor-navigator-menu-btn').trigger('click')
  return { wrapper, spy }
}

describe('NavigatorNode Move Up/Down', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('Move Up reorders to index-1 with a single move-component command', async () => {
    const { wrapper, spy } = await mountWithSiblings('b2') // index 1
    await item(wrapper, 'Move Up').trigger('click')
    expect(spy).toHaveBeenCalledTimes(1)
    expect(spy).toHaveBeenCalledWith('move-component', { component_id: 'b2', target_parent_id: 'sec', index: 0 })
  })

  it('Move Down reorders to index+1', async () => {
    const { wrapper, spy } = await mountWithSiblings('b2') // index 1
    await item(wrapper, 'Move Down').trigger('click')
    expect(spy).toHaveBeenCalledWith('move-component', { component_id: 'b2', target_parent_id: 'sec', index: 2 })
  })

  it('disables Move Up on the first sibling and Move Down on the last', async () => {
    const first = await mountWithSiblings('b1')
    expect(item(first.wrapper, 'Move Up').attributes('disabled')).toBeDefined()
    expect(item(first.wrapper, 'Move Down').attributes('disabled')).toBeUndefined()

    const last = await mountWithSiblings('b3')
    expect(item(last.wrapper, 'Move Up').attributes('disabled')).toBeUndefined()
    expect(item(last.wrapper, 'Move Down').attributes('disabled')).toBeDefined()
  })
})
