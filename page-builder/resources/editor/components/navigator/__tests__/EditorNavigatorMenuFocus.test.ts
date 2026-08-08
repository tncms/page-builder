import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { mount, flushPromises } from '@vue/test-utils'
import { nextTick } from 'vue'
import EditorNavigator from '../EditorNavigator.vue'
import { useEditorStore } from '../../../stores/editor'
import type { LayoutPayload, EditorMetadata } from '../../../types'

// Focus lifecycle (Increment 4.2, Step 4). Real DOM focus is exercised, so these
// mounts attach to the document. Tree: sec1 → [b1, b2]; roving focus is distinct
// from selection.
function payload(): LayoutPayload {
  return {
    version: 1,
    sections: [{ id: 'sec1', type: 'hero', data: {}, blocks: [{ id: 'b1', type: 'heading', data: {} }, { id: 'b2', type: 'heading', data: {} }] }],
  } as unknown as LayoutPayload
}

const metadata = {
  version: '1',
  sections: [{ type: 'hero', label: 'Hero' }],
  blocks: [{ type: 'heading', label: 'Heading' }],
  tokens: {},
} as unknown as EditorMetadata

describe('Navigator context-menu focus lifecycle (Increment 4.2)', () => {
  let wrapper: ReturnType<typeof mount> | null = null

  beforeEach(() => {
    setActivePinia(createPinia())
    const store = useEditorStore()
    store.currentPayload = payload()
    store.metadata = metadata
  })
  afterEach(() => {
    wrapper?.unmount()
    wrapper = null
  })

  async function openFocusedMenuOnB1() {
    wrapper = mount(EditorNavigator, { attachTo: document.body })
    const tree = wrapper.find('[role="tree"]')
    ;(tree.element as HTMLElement).focus()
    await tree.trigger('focus') // init roving focus to first row
    await tree.trigger('keydown', { key: 'ArrowDown' }) // focus b1 (not selected)
    await tree.trigger('keydown', { key: 'F10', shiftKey: true }) // keyboard-open
    await flushPromises()
    return { tree }
  }

  it('keyboard-open focuses the first enabled menu item', async () => {
    await openFocusedMenuOnB1()
    expect(document.activeElement?.getAttribute('role')).toBe('menuitem')
  })

  it('opening the menu on a focused-but-unselected row leaves selection unchanged', async () => {
    const store = useEditorStore()
    expect(store.selectedComponentId).not.toBe('b1')
    await openFocusedMenuOnB1()
    // Menu is open and belongs to b1, yet selection was never touched.
    expect(wrapper!.find('.pb-editor-navigator-menu').exists()).toBe(true)
    expect(store.selectedComponentId).not.toBe('b1')
  })

  it('Escape closes the menu and restores focus to the originating tree', async () => {
    const { tree } = await openFocusedMenuOnB1()
    const menu = wrapper!.find('.pb-editor-navigator-menu')
    await menu.trigger('keydown', { key: 'Escape' })
    await nextTick()
    expect(wrapper!.find('.pb-editor-navigator-menu').exists()).toBe(false)
    expect(document.activeElement).toBe(tree.element)
  })
})
