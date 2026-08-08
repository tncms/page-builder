import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { mount, type VueWrapper } from '@vue/test-utils'
import { computed, nextTick } from 'vue'
import NavigatorNode from '../NavigatorNode.vue'
import { NavigatorLocksKey } from '../injection'
import { useEditorStore } from '../../../stores/editor'
import { useNavigatorLocksStore } from '../../../stores/navigatorLocks'
import { buildNodeTree } from '../../../utils/layout'
import type { ComponentNode, EditorMetadata, LayoutPayload } from '../../../types'

// NavigatorNode structural insertion wiring (Increment 6): the context-menu Add
// Inside/Before/After actions open the reusable picker and commit through the
// store's single insertComponent command with the correct parent + index. Menu
// visibility follows metadata + NestingPolicy; locks disable insertion.

function payload(): LayoutPayload {
  return {
    version: 1,
    sections: [
      {
        id: 'sec1',
        type: 'section',
        data: {},
        blocks: [
          {
            id: 'ctr',
            type: 'container',
            data: {
              blocks: [
                { id: 'col1', type: 'column', data: { width: '50', blocks: [{ id: 'btn', type: 'button', data: {} }] } },
                { id: 'col2', type: 'column', data: { width: '50', blocks: [{ id: 'txt', type: 'text', data: {} }] } },
              ],
            },
          },
        ],
      },
    ],
  } as unknown as LayoutPayload
}

const metadata = {
  version: '1',
  sections: [{ type: 'section', label: 'Section' }, { type: 'columns', label: 'Columns' }],
  blocks: [
    { type: 'container', label: 'Container' },
    { type: 'column', label: 'Column' },
    { type: 'heading', label: 'Heading' },
    { type: 'button', label: 'Button' },
    { type: 'text', label: 'Text' },
  ],
  tokens: {},
} as unknown as EditorMetadata

function nodeFor(store: ReturnType<typeof useEditorStore>, id: string): ComponentNode {
  const find = (nodes: ComponentNode[]): ComponentNode | null => {
    for (const n of nodes) {
      if (n.id === id) return n
      const hit = find(n.children)
      if (hit) return hit
    }
    return null
  }
  const hit = find(buildNodeTree(store.currentPayload))
  if (!hit) throw new Error(`node ${id} not found`)
  return hit
}

let active: VueWrapper | null = null
afterEach(() => {
  active?.unmount()
  active = null
})

function scene() {
  setActivePinia(createPinia())
  const store = useEditorStore()
  store.currentPayload = payload()
  store.metadata = metadata
  return store
}

async function mountNode(store: ReturnType<typeof useEditorStore>, id: string, locked = false) {
  const wrapper = mount(NavigatorNode, {
    props: { node: nodeFor(store, id) },
    attachTo: document.body,
    global: locked ? { provide: { [NavigatorLocksKey as symbol]: computed(() => new Set([id])) } } : {},
  })
  active = wrapper
  return wrapper
}

async function openMenu(w: VueWrapper) {
  await w.find('.pb-editor-navigator-menu-btn').trigger('click')
}
const menuItem = (w: VueWrapper, text: string) =>
  w.findAll('.pb-editor-navigator-menu [role="menuitem"]').find((b) => b.text() === text)
const pickerItems = (w: VueWrapper) => w.findAll('.pb-editor-navigator-picker [role="menuitem"]')

describe('NavigatorNode — structural insertion', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('a container offers Add Inside/Before/After; a leaf offers only Before/After', async () => {
    const store = scene()
    const ctr = await mountNode(store, 'ctr')
    await openMenu(ctr)
    for (const label of ['Add Inside', 'Add Before', 'Add After']) expect(menuItem(ctr, label)).toBeTruthy()
    ctr.unmount()

    const btn = await mountNode(store, 'btn')
    await openMenu(btn)
    expect(menuItem(btn, 'Add Inside')).toBeUndefined() // a button cannot hold children
    expect(menuItem(btn, 'Add Before')).toBeTruthy()
    expect(menuItem(btn, 'Add After')).toBeTruthy()
  })

  it('Add Inside on a container picks a column and commits via insertComponent, expanding the parent', async () => {
    const store = scene()
    const insert = vi.spyOn(store, 'insertComponent').mockResolvedValue('newcol')
    const expand = vi.spyOn(store, 'expandComponent').mockImplementation(() => {})
    const wrapper = await mountNode(store, 'ctr')
    await openMenu(wrapper)
    await menuItem(wrapper, 'Add Inside')!.trigger('click')

    // Menu closed; the picker opened with the only valid candidate: Column.
    expect(wrapper.find('.pb-editor-navigator-menu').exists()).toBe(false)
    expect(pickerItems(wrapper).map((b) => b.find('.pb-editor-navigator-picker__type').text())).toEqual(['column'])

    await pickerItems(wrapper)[0].trigger('click')
    expect(insert).toHaveBeenCalledWith({ parentId: 'ctr', componentType: 'column', index: null })
    await nextTick()
    expect(expand).toHaveBeenCalledWith('ctr')
    expect(wrapper.find('.pb-editor-navigator-picker').exists()).toBe(false) // picker closed after choosing
  })

  it('Add After on a leaf inserts a sibling at index + 1 in the leaf’s parent', async () => {
    const store = scene()
    const insert = vi.spyOn(store, 'insertComponent').mockResolvedValue('newx')
    const wrapper = await mountNode(store, 'btn') // btn is index 0 in col1
    await openMenu(wrapper)
    await menuItem(wrapper, 'Add After')!.trigger('click')
    // Column children: button/text/heading are valid (column rejects column/container).
    expect(pickerItems(wrapper).map((b) => b.find('.pb-editor-navigator-picker__type').text())).toEqual(['heading', 'button', 'text'])
    await pickerItems(wrapper).find((b) => b.find('.pb-editor-navigator-picker__type').text() === 'text')!.trigger('click')
    expect(insert).toHaveBeenCalledWith({ parentId: 'col1', componentType: 'text', index: 1 })
  })

  it('Add Before on a column inserts a column sibling at the column’s index in the container', async () => {
    const store = scene()
    const insert = vi.spyOn(store, 'insertComponent').mockResolvedValue('newcol')
    const wrapper = await mountNode(store, 'col2') // col2 is index 1 in ctr
    await openMenu(wrapper)
    await menuItem(wrapper, 'Add Before')!.trigger('click')
    await pickerItems(wrapper)[0].trigger('click')
    expect(insert).toHaveBeenCalledWith({ parentId: 'ctr', componentType: 'column', index: 1 })
  })

  it('a locked node disables all three insertion actions', async () => {
    const store = scene()
    useNavigatorLocksStore().lock(store.owner, 'ctr')
    const wrapper = await mountNode(store, 'ctr', true)
    await openMenu(wrapper)
    for (const label of ['Add Inside', 'Add Before', 'Add After']) {
      expect(menuItem(wrapper, label)!.attributes('disabled')).toBeDefined()
    }
  })
})
