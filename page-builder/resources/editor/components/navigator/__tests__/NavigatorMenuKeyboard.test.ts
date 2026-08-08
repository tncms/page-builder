import { describe, it, expect, afterEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { mount, flushPromises } from '@vue/test-utils'
import { nextTick } from 'vue'
import NavigatorNode from '../NavigatorNode.vue'
import { useEditorStore } from '../../../stores/editor'
import type { ComponentNode, LayoutPayload } from '../../../types'

// A row's context menu is a roving-tabindex `role="menu"` (Increment 4.2). These
// mounts attach to the real document so `.focus()` / `document.activeElement`
// behave, and cover: keyboard roving, disabled-item skipping, activation, and
// mouse-vs-keyboard open behavior. Siblings b1/b2/b3 give a deterministic
// boundary — Move Up is disabled on the first sibling, so it exercises skipping.
function block(id: string, disabled = false): ComponentNode {
  return { id, type: 'heading', kind: 'block', parentId: 'sec', depth: 1, disabled, data: {}, children: [] }
}

function siblingsPayload(): LayoutPayload {
  return {
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
  } as unknown as LayoutPayload
}

function mountNode(id: string, disabled = false) {
  setActivePinia(createPinia())
  const store = useEditorStore()
  store.currentPayload = siblingsPayload()
  const wrapper = mount(NavigatorNode, { props: { node: block(id, disabled) }, attachTo: document.body })
  return { wrapper, store }
}

const menu = (w: ReturnType<typeof mountNode>['wrapper']) => w.find('.pb-editor-navigator-menu')
const focusedText = () => (document.activeElement?.textContent ?? '').trim()

async function openMouse(w: ReturnType<typeof mountNode>['wrapper']) {
  await w.find('.pb-editor-navigator-menu-btn').trigger('click')
  await nextTick()
}

describe('Navigator context-menu keyboard roving (Increment 4.2)', () => {
  let active: ReturnType<typeof mountNode>['wrapper'] | null = null
  afterEach(() => {
    active?.unmount()
    active = null
  })

  it('mouse open does not steal focus into the menu', async () => {
    const { wrapper } = mountNode('b1')
    active = wrapper
    await openMouse(wrapper)
    expect(menu(wrapper).exists()).toBe(true)
    expect(document.activeElement?.getAttribute('role')).not.toBe('menuitem')
  })

  it('ArrowDown/ArrowUp rove enabled items and skip a disabled one', async () => {
    const { wrapper } = mountNode('b1') // first sibling → Move Up disabled
    active = wrapper
    await openMouse(wrapper)
    await menu(wrapper).trigger('keydown', { key: 'ArrowDown' })
    expect(focusedText()).toBe('Rename') // first enabled item
    await menu(wrapper).trigger('keydown', { key: 'ArrowDown' })
    expect(focusedText()).toBe('Move Down') // disabled Move Up skipped
    await menu(wrapper).trigger('keydown', { key: 'ArrowDown' })
    expect(focusedText()).toBe('Duplicate')
    await menu(wrapper).trigger('keydown', { key: 'ArrowUp' })
    expect(focusedText()).toBe('Move Down')
  })

  it('Home focuses the first enabled item, End focuses the last', async () => {
    const { wrapper } = mountNode('b1')
    active = wrapper
    await openMouse(wrapper)
    await menu(wrapper).trigger('keydown', { key: 'End' })
    expect(focusedText()).toBe('Delete')
    await menu(wrapper).trigger('keydown', { key: 'Home' })
    expect(focusedText()).toBe('Rename')
  })

  it('ArrowDown wraps from the last enabled item back to the first', async () => {
    const { wrapper } = mountNode('b1')
    active = wrapper
    await openMouse(wrapper)
    await menu(wrapper).trigger('keydown', { key: 'End' }) // Delete (last)
    await menu(wrapper).trigger('keydown', { key: 'ArrowDown' })
    expect(focusedText()).toBe('Rename') // wrapped to first enabled
  })

  it('Enter activates the focused item', async () => {
    const { wrapper, store } = mountNode('b1')
    active = wrapper
    const spy = vi.spyOn(store, 'executeCommand').mockResolvedValue(true)
    await openMouse(wrapper)
    await menu(wrapper).trigger('keydown', { key: 'ArrowDown' }) // Rename
    await menu(wrapper).trigger('keydown', { key: 'ArrowDown' }) // Move Down
    await menu(wrapper).trigger('keydown', { key: 'Enter' })
    await flushPromises()
    expect(spy).toHaveBeenCalledTimes(1)
    expect(spy).toHaveBeenCalledWith('move-component', expect.objectContaining({ component_id: 'b1' }))
  })

  it('Space activates the focused item', async () => {
    const { wrapper, store } = mountNode('b1')
    active = wrapper
    const spy = vi.spyOn(store, 'executeCommand').mockResolvedValue(true)
    await openMouse(wrapper)
    await menu(wrapper).trigger('keydown', { key: 'End' }) // focus Delete
    await menu(wrapper).trigger('keydown', { key: ' ' })
    await flushPromises()
    expect(spy).toHaveBeenCalledWith('remove-component', { component_id: 'b1' })
  })

  it('Escape closes the menu', async () => {
    const { wrapper } = mountNode('b1')
    active = wrapper
    await openMouse(wrapper)
    expect(menu(wrapper).exists()).toBe(true)
    await menu(wrapper).trigger('keydown', { key: 'Escape' })
    expect(menu(wrapper).exists()).toBe(false)
  })

  // Increment 4.2 audit outcome: `node.disabled` is editor-only display state, NOT
  // canonical public visibility, so NO Hide/Show action is exposed. A disabled node
  // still dims in the tree and its menu carries no misleading visibility toggle.
  it('a disabled node dims but exposes no Hide/Show action', async () => {
    const { wrapper } = mountNode('b1', true)
    active = wrapper
    expect(wrapper.find('.pb-editor-navigator-row.is-disabled').exists()).toBe(true)
    await openMouse(wrapper)
    const labels = menu(wrapper)
      .findAll('button[role="menuitem"]')
      .map((b) => b.text())
    expect(labels).not.toContain('Hide')
    expect(labels).not.toContain('Show')
  })
})
