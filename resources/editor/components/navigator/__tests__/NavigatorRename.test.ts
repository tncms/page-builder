import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { mount, flushPromises } from '@vue/test-utils'
import { computed, nextTick } from 'vue'
import NavigatorNode from '../NavigatorNode.vue'
import EditorNavigator from '../EditorNavigator.vue'
import { NavigatorLocksKey } from '../injection'
import { useEditorStore } from '../../../stores/editor'
import { useNavigatorLocksStore } from '../../../stores/navigatorLocks'
import type { ComponentNode, LayoutPayload, EditorMetadata } from '../../../types'

function block(id: string): ComponentNode {
  return { id, type: 'heading', kind: 'block', parentId: 'sec', depth: 1, disabled: false, data: {}, children: [] }
}

function siblings(): LayoutPayload {
  return {
    version: 1,
    sections: [{ id: 'sec', type: 'hero', data: {}, blocks: [{ id: 'b1', type: 'heading', data: {} }, { id: 'b2', type: 'heading', data: {} }] }],
  } as unknown as LayoutPayload
}

const renameInput = (w: ReturnType<typeof mount>) => w.find('.pb-editor-navigator-rename')
const labelSpan = (w: ReturnType<typeof mount>) => w.find('span.pb-editor-navigator-label')
const menuItem = (w: ReturnType<typeof mount>, label: string) =>
  w.findAll('button[role="menuitem"]').find((b) => b.text() === label)!

describe('Navigator inline rename — UI (Increment 5)', () => {
  let store: ReturnType<typeof useEditorStore>
  let spy: ReturnType<typeof vi.spyOn>

  beforeEach(() => {
    setActivePinia(createPinia())
    store = useEditorStore()
    store.currentPayload = siblings()
    spy = vi.spyOn(store, 'executeCommand').mockResolvedValue(true)
  })

  it('double click on the label opens the rename input', async () => {
    const wrapper = mount(NavigatorNode, { props: { node: block('b1') } })
    await labelSpan(wrapper).trigger('dblclick')
    await nextTick()
    expect(renameInput(wrapper).exists()).toBe(true)
  })

  it('context-menu Rename opens the input and closes the menu', async () => {
    const wrapper = mount(NavigatorNode, { props: { node: block('b1') } })
    await wrapper.find('.pb-editor-navigator-menu-btn').trigger('click')
    await menuItem(wrapper, 'Rename').trigger('click')
    await nextTick()
    expect(renameInput(wrapper).exists()).toBe(true)
    expect(wrapper.find('.pb-editor-navigator-menu').exists()).toBe(false)
  })

  it('typing dispatches no command', async () => {
    const wrapper = mount(NavigatorNode, { props: { node: block('b1') } })
    await labelSpan(wrapper).trigger('dblclick')
    await renameInput(wrapper).setValue('Work in progress')
    expect(spy).not.toHaveBeenCalled()
  })

  it('Enter commits the rename through update-component-data', async () => {
    const wrapper = mount(NavigatorNode, { props: { node: block('b1') } })
    await labelSpan(wrapper).trigger('dblclick')
    await renameInput(wrapper).setValue('New Name')
    await renameInput(wrapper).trigger('keydown', { key: 'Enter' })
    await flushPromises()
    expect(spy).toHaveBeenCalledWith('update-component-data', { component_id: 'b1', data: { _label: 'New Name' } })
    expect(renameInput(wrapper).exists()).toBe(false) // editing ended
  })

  it('Escape cancels: no command, original label restored', async () => {
    const wrapper = mount(NavigatorNode, { props: { node: block('b1') } })
    await labelSpan(wrapper).trigger('dblclick')
    await renameInput(wrapper).setValue('Discard me')
    await renameInput(wrapper).trigger('keydown', { key: 'Escape' })
    await nextTick()
    expect(spy).not.toHaveBeenCalled()
    expect(renameInput(wrapper).exists()).toBe(false)
    expect(labelSpan(wrapper).text()).toBe('Heading') // fallback label unchanged
  })

  it('a locked node cannot open rename (double click no-ops, menu item disabled)', async () => {
    const locks = useNavigatorLocksStore()
    locks.lock(store.owner, 'b1')
    const wrapper = mount(NavigatorNode, {
      props: { node: block('b1') },
      global: { provide: { [NavigatorLocksKey as symbol]: computed(() => locks.lockedSet(store.owner)) } },
    })
    await labelSpan(wrapper).trigger('dblclick')
    await nextTick()
    expect(renameInput(wrapper).exists()).toBe(false)
    await wrapper.find('.pb-editor-navigator-menu-btn').trigger('click')
    expect(menuItem(wrapper, 'Rename').attributes('disabled')).toBeDefined()
  })
})

// ---- F2 + focus lifecycle (real DOM focus, attached mounts) ----------------
const metadata = {
  version: '1',
  sections: [{ type: 'hero', label: 'Hero' }],
  blocks: [{ type: 'heading', label: 'Heading' }],
  tokens: {},
} as unknown as EditorMetadata

describe('Navigator inline rename — F2 + focus (Increment 5)', () => {
  let wrapper: ReturnType<typeof mount> | null = null
  beforeEach(() => {
    setActivePinia(createPinia())
    const store = useEditorStore()
    store.currentPayload = siblings()
    store.metadata = metadata
  })
  afterEach(() => {
    wrapper?.unmount()
    wrapper = null
  })

  async function focusB1AndF2() {
    wrapper = mount(EditorNavigator, { attachTo: document.body })
    const tree = wrapper.find('[role="tree"]')
    ;(tree.element as HTMLElement).focus()
    await tree.trigger('focus')
    await tree.trigger('keydown', { key: 'ArrowDown' }) // focus b1 (unselected)
    await tree.trigger('keydown', { key: 'F2' })
    await flushPromises()
    return { tree }
  }

  it('F2 opens rename on the focused (unselected) row and autofocuses the input', async () => {
    const store = useEditorStore()
    await focusB1AndF2()
    const input = wrapper!.find('.pb-editor-navigator-rename')
    expect(input.exists()).toBe(true)
    expect(document.activeElement).toBe(input.element)
    expect(store.selectedComponentId).not.toBe('b1') // selection untouched
  })

  it('Escape ends rename and restores focus to the tree', async () => {
    const { tree } = await focusB1AndF2()
    await wrapper!.find('.pb-editor-navigator-rename').trigger('keydown', { key: 'Escape' })
    await nextTick()
    expect(wrapper!.find('.pb-editor-navigator-rename').exists()).toBe(false)
    expect(document.activeElement).toBe(tree.element)
  })

  it('Enter commits and restores focus to the tree', async () => {
    const store = useEditorStore()
    const spy = vi.spyOn(store, 'executeCommand').mockResolvedValue(true)
    const { tree } = await focusB1AndF2()
    const input = wrapper!.find('.pb-editor-navigator-rename')
    await input.setValue('Renamed via F2')
    await input.trigger('keydown', { key: 'Enter' })
    await flushPromises()
    expect(spy).toHaveBeenCalledWith('update-component-data', { component_id: 'b1', data: { _label: 'Renamed via F2' } })
    expect(wrapper!.find('.pb-editor-navigator-rename').exists()).toBe(false)
    expect(document.activeElement).toBe(tree.element)
  })
})
