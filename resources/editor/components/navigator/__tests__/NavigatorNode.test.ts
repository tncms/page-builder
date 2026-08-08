// HOTFIX regression — Navigator context-menu actions must actually dispatch.
//
// The original bug: `runMenu` RETURNED a click handler that Vue never invoked
// (Vue compiles `@click="runMenu(fn)"` to `$event => runMenu(fn)` and discards
// the return value), so every navigator action was a silent no-op. These tests
// mount the real NavigatorNode and click real DOM buttons — a test that only
// called the store method directly would have passed against the broken code.
import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { mount } from '@vue/test-utils'
import NavigatorNode from '../NavigatorNode.vue'
import { useEditorStore } from '../../../stores/editor'
import { buildNodeTree } from '../../../utils/layout'
import type { ComponentNode, EditorMetadata, LayoutPayload, StateResult } from '../../../types'

// A composition tree: Section → Container → [Column(Button), Column(Text)],
// plus a sibling legacy "Columns" section holding two leaf blocks.
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
      {
        id: 'legacy',
        type: 'columns',
        data: {},
        blocks: [
          { id: 'lbtn', type: 'button', data: {} },
          { id: 'ltxt', type: 'text', data: {} },
        ],
      },
    ],
  } as unknown as LayoutPayload
}

const metadata = {
  version: '1',
  sections: [
    { type: 'section', label: 'Section' },
    { type: 'columns', label: 'Columns' },
  ],
  blocks: [
    { type: 'container', label: 'Container' },
    { type: 'column', label: 'Column' },
    { type: 'button', label: 'Button' },
    { type: 'text', label: 'Text' },
  ],
  tokens: {},
} as unknown as EditorMetadata

// A fake command endpoint that removes the node by id (mirrors the server's
// remove-component so the store's `changed` check + reactive tree see a real
// mutation). Returns a null server selection so the store's own fallback runs.
function removeFromPayload(p: LayoutPayload, id: string): LayoutPayload {
  const clone = JSON.parse(JSON.stringify(p)) as LayoutPayload
  const strip = (blocks: any[]): any[] =>
    blocks
      .filter((b) => b.id !== id)
      .map((b) => (b.data?.blocks ? { ...b, data: { ...b.data, blocks: strip(b.data.blocks) } } : b))
  clone.sections = (clone.sections ?? [])
    .filter((s: any) => s.id !== id)
    .map((s: any) => ({ ...s, blocks: strip(s.blocks ?? []) }))
  return clone
}

function makeStore() {
  setActivePinia(createPinia())
  const store = useEditorStore()
  store.currentPayload = payload()
  store.metadata = metadata
  store.client = {
    async runCommand(before: LayoutPayload, command: string, params: Record<string, unknown>): Promise<StateResult> {
      if (command === 'remove-component') {
        return { payload: removeFromPayload(before, params.component_id as string), selection: { selected: null } }
      }
      return { payload: before, selection: { selected: null } } as StateResult
    },
  } as never
  return store
}

// Find the raw node object for an id from the freshly built tree.
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

async function mountNode(store: ReturnType<typeof useEditorStore>, id: string) {
  const wrapper = mount(NavigatorNode, { props: { node: nodeFor(store, id) } })
  // Open the per-node action menu.
  await wrapper.find('.pb-editor-navigator-menu-btn').trigger('click')
  return wrapper
}

const ids = (store: ReturnType<typeof useEditorStore>): string[] => {
  const out: string[] = []
  const walk = (nodes: ComponentNode[]) => nodes.forEach((n) => (out.push(n.id), walk(n.children)))
  walk(buildNodeTree(store.currentPayload))
  return out
}

describe('NavigatorNode — context menu actions (hotfix)', () => {
  let store: ReturnType<typeof useEditorStore>

  beforeEach(() => {
    store = makeStore()
  })

  it('Delete on the selected Column dispatches deleteComponent with the node id', async () => {
    store.selectComponent('col1')
    const spy = vi.spyOn(store, 'deleteComponent')
    const wrapper = await mountNode(store, 'col1')

    await wrapper.find('.pb-editor-navigator-menu__danger').trigger('click')

    expect(spy).toHaveBeenCalledTimes(1)
    expect(spy).toHaveBeenCalledWith('col1')
  })

  it('Delete on an UNSELECTED Column targets that node, not the selection', async () => {
    store.selectComponent('btn') // a different node is selected
    const spy = vi.spyOn(store, 'deleteComponent')
    const wrapper = await mountNode(store, 'col2')

    await wrapper.find('.pb-editor-navigator-menu__danger').trigger('click')

    expect(spy).toHaveBeenCalledWith('col2')
    expect(spy).not.toHaveBeenCalledWith('btn')
  })

  it('closing the menu does not clear the id before the handler runs (menu closes AFTER dispatch)', async () => {
    store.selectComponent('col1')
    const wrapper = await mountNode(store, 'col1')
    expect(wrapper.find('.pb-editor-navigator-menu').exists()).toBe(true)

    await wrapper.find('.pb-editor-navigator-menu__danger').trigger('click')
    await wrapper.vm.$nextTick()

    // Menu is gone AND the delete actually mutated the payload.
    expect(wrapper.find('.pb-editor-navigator-menu').exists()).toBe(false)
    expect(ids(store)).not.toContain('col1')
  })

  it('Duplicate / Copy / Cut also dispatch with the node id', async () => {
    const dup = vi.spyOn(store, 'duplicateComponent').mockResolvedValue()
    const copy = vi.spyOn(store, 'copyComponent').mockImplementation(() => {})
    const cut = vi.spyOn(store, 'cutComponent').mockResolvedValue()
    const wrapper = await mountNode(store, 'col1')

    // Select menu items by label so added items (Copy ID, Lock, Move Up/Down) never shift these.
    const byText = (label: string) =>
      wrapper.findAll('.pb-editor-navigator-menu button').find((b) => b.text() === label)!

    await byText('Duplicate').trigger('click')
    expect(dup).toHaveBeenCalledWith('col1')

    await wrapper.find('.pb-editor-navigator-menu-btn').trigger('click')
    await byText('Copy').trigger('click')
    expect(copy).toHaveBeenCalledWith('col1')

    await wrapper.find('.pb-editor-navigator-menu-btn').trigger('click')
    await byText('Cut').trigger('click')
    expect(cut).toHaveBeenCalledWith('col1')
  })

  it('deletes a composition Column and updates the payload tree', async () => {
    store.selectComponent('col1')
    const wrapper = await mountNode(store, 'col1')
    await wrapper.find('.pb-editor-navigator-menu__danger').trigger('click')
    await wrapper.vm.$nextTick()
    expect(ids(store)).not.toContain('col1')
    expect(ids(store)).toContain('col2')
  })

  it('deletes a legacy Columns child and updates the payload tree', async () => {
    store.selectComponent('lbtn')
    const wrapper = await mountNode(store, 'lbtn')
    await wrapper.find('.pb-editor-navigator-menu__danger').trigger('click')
    await wrapper.vm.$nextTick()
    expect(ids(store)).not.toContain('lbtn')
    expect(ids(store)).toContain('ltxt')
  })

  it('deleting the last Column leaves the parent Container empty (not recreated)', async () => {
    // Remove both columns; the container must survive as an empty parent.
    store.selectComponent('col1')
    await store.deleteComponent('col1')
    await store.deleteComponent('col2')
    expect(ids(store)).toContain('ctr')
    expect(ids(store)).not.toContain('col1')
    expect(ids(store)).not.toContain('col2')
  })

  it('menu items are exposed as ARIA menuitems', async () => {
    const wrapper = await mountNode(store, 'col1')
    expect(wrapper.find('.pb-editor-navigator-menu').attributes('role')).toBe('menu')
    const items = wrapper.findAll('.pb-editor-navigator-menu [role="menuitem"]')
    // Phase 26.3 menu for a Column (metadata set, so insertion actions surface):
    // Rename, Add Inside, Add Before, Add After, Move Up, Move Down, Duplicate,
    // Copy, Copy ID, Cut, Paste inside, Lock, Delete.
    expect(items.length).toBe(13)
  })
})
