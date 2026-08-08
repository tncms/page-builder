import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { mount, flushPromises, type VueWrapper } from '@vue/test-utils'
import { nextTick } from 'vue'
import PropertyInspector from '../../inspector/PropertyInspector.vue'
import { useEditorStore } from '../../../stores/editor'
import { useNavigatorLocksStore } from '../../../stores/navigatorLocks'
import { clonePlain } from '../../../utils/dataPath'
import type { LayoutPayload, EditorMetadata } from '../../../types'

// store.renameComponent (Increment 5): the commit path. Reuses update-component-data
// (one command, one undo snapshot), the lock guard, and the inspector dirty guard.
// The custom name lives under the inert `_label` meta key — no new storage.

function findById(node: any, id: string): any {
  for (const section of node.sections ?? []) {
    if (section.id === id) return section
    for (const block of section.blocks ?? []) if (block.id === id) return block
  }
  return null
}

function renameScene() {
  setActivePinia(createPinia())
  const store = useEditorStore()
  store.currentPayload = {
    version: 1,
    sections: [{ id: 'sec', type: 'hero', data: {}, blocks: [{ id: 'b1', type: 'heading', data: { text: 'hi' } }] }],
  } as unknown as LayoutPayload
  store.metadata = { version: '1', sections: [{ type: 'hero', label: 'Hero' }], blocks: [{ type: 'heading', label: 'Heading' }], tokens: {} } as unknown as EditorMetadata
  // Realistic backend: update-component-data SHALLOW-MERGES into the node's data.
  const runCommand = vi.fn(async (before: LayoutPayload, command: string, params: Record<string, unknown>) => {
    if (command === 'update-component-data') {
      const next = clonePlain(before) as any
      const node = findById(next, params.component_id as string)
      if (node) node.data = { ...node.data, ...(params.data as object) }
      return { payload: next, selection: { selected: params.component_id } }
    }
    return { payload: before, selection: { selected: null } }
  })
  ;(store as unknown as { client: unknown }).client = { runCommand }
  return { store, runCommand }
}

const b1Data = (store: ReturnType<typeof useEditorStore>) => (findById(store.currentPayload, 'b1')?.data ?? {}) as Record<string, unknown>

describe('store.renameComponent — command + undo', () => {
  it('commits a custom label as one command with exactly one undo snapshot', async () => {
    const { store, runCommand } = renameScene()
    expect(store.undoStack.length).toBe(0)

    const ok = await store.renameComponent('b1', 'Hero Title')
    expect(ok).toBe(true)
    expect(b1Data(store)._label).toBe('Hero Title')
    expect(b1Data(store).text).toBe('hi') // other data preserved (shallow merge)
    expect(runCommand).toHaveBeenCalledTimes(1)
    expect(store.undoStack.length).toBe(1)
  })

  it('trims and caps length', async () => {
    const { store } = renameScene()
    await store.renameComponent('b1', '   Spaced Name   ')
    expect(b1Data(store)._label).toBe('Spaced Name')
    await store.renameComponent('b1', 'x'.repeat(500))
    expect((b1Data(store)._label as string).length).toBe(120)
  })

  it('empty value clears the custom label (falls back), as one command', async () => {
    const { store } = renameScene()
    await store.renameComponent('b1', 'Custom')
    expect(b1Data(store)._label).toBe('Custom')
    const ok = await store.renameComponent('b1', '   ')
    expect(ok).toBe(true)
    expect(b1Data(store)._label).toBe('') // cleared → row falls back to the type label
  })

  it('typing back the fallback (type) label stores no custom name and no command', async () => {
    const { store, runCommand } = renameScene()
    const ok = await store.renameComponent('b1', 'Heading') // the definition/fallback label
    expect(ok).toBe(true)
    expect(b1Data(store)._label).toBeUndefined()
    expect(runCommand).not.toHaveBeenCalled()
    expect(store.undoStack.length).toBe(0)
  })

  it('committing the unchanged value is a no-op (no command, no undo)', async () => {
    const { store, runCommand } = renameScene()
    await store.renameComponent('b1', 'Same')
    runCommand.mockClear()
    const ok = await store.renameComponent('b1', 'Same')
    expect(ok).toBe(true)
    expect(runCommand).not.toHaveBeenCalled()
  })

  it('a locked node cannot be renamed', async () => {
    const { store, runCommand } = renameScene()
    useNavigatorLocksStore().lock(store.owner, 'b1')
    const ok = await store.renameComponent('b1', 'Nope')
    expect(ok).toBe(false)
    expect(runCommand).not.toHaveBeenCalled()
    expect(store.undoStack.length).toBe(0)
  })
})

// ------- Dirty guard reused before a rename commit (no second dialog) --------
const metadata = {
  version: '1',
  sections: [{ type: 'hero', label: 'Hero' }],
  blocks: [
    { type: 'button', label: 'Button', schema: { tabs: [{ name: 'content', label: 'Content', fields: [{ name: 'note', type: 'textarea', label: 'Note' }] }] } },
  ],
  tokens: {},
} as unknown as EditorMetadata

function inspectorScene(rejectApply = false) {
  setActivePinia(createPinia())
  const store = useEditorStore()
  store.currentPayload = {
    version: 1,
    sections: [{ id: 'sec1', type: 'hero', data: {}, blocks: [{ id: 'btn1', type: 'button', data: { note: '' } }] }],
  } as unknown as LayoutPayload
  store.metadata = metadata
  store.boot = { locale: 'vi' } as never
  const runCommand = vi.fn(async (before: LayoutPayload, command: string, params: Record<string, unknown>) => {
    if (command === 'update-component-data') {
      if (rejectApply && (params.data as Record<string, unknown>).note !== undefined) throw new Error('apply failed')
      const next = clonePlain(before) as any
      const node = findById(next, params.component_id as string)
      if (node) node.data = { ...node.data, ...(params.data as object) }
      return { payload: next, selection: { selected: params.component_id } }
    }
    return { payload: before, selection: { selected: null } }
  })
  ;(store as unknown as { client: unknown }).client = { runCommand }
  store.selectComponent('btn1')
  return { store }
}

const dialogBtn = (w: VueWrapper, text: string) =>
  w.findAll('.pb-inspector-dialog__actions .pb-editor-btn').find((b) => b.text().includes(text))!
const stageDraft = (w: VueWrapper) => w.find('.pb-inspector-field[data-field="note"] textarea').setValue('edited')
const renameCommitted = (store: ReturnType<typeof useEditorStore>) =>
  ((findById(store.currentPayload, 'btn1')?.data ?? {}) as Record<string, unknown>)._label === 'Renamed'

describe('renameComponent reaches the inspector dirty guard', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('Apply & Continue applies the draft then commits the rename', async () => {
    const { store } = inspectorScene()
    const wrapper = mount(PropertyInspector)
    await stageDraft(wrapper)
    const pending = store.renameComponent('btn1', 'Renamed')
    await nextTick()
    expect(wrapper.find('.pb-inspector-dialog').exists()).toBe(true)
    await dialogBtn(wrapper, 'Apply').trigger('click')
    await flushPromises()
    expect(await pending).toBe(true)
    expect(renameCommitted(store)).toBe(true)
    wrapper.unmount()
  })

  it('Stay Here blocks the rename', async () => {
    const { store } = inspectorScene()
    const wrapper = mount(PropertyInspector)
    await stageDraft(wrapper)
    const pending = store.renameComponent('btn1', 'Renamed')
    await nextTick()
    await dialogBtn(wrapper, 'Stay').trigger('click')
    await flushPromises()
    expect(await pending).toBe(false)
    expect(renameCommitted(store)).toBe(false)
    wrapper.unmount()
  })

  it('a failed Apply blocks the rename and keeps the dialog open', async () => {
    const { store } = inspectorScene(true)
    const wrapper = mount(PropertyInspector)
    await stageDraft(wrapper)
    const pending = store.renameComponent('btn1', 'Renamed')
    await nextTick()
    await dialogBtn(wrapper, 'Apply').trigger('click')
    await flushPromises()
    expect(wrapper.find('.pb-inspector-dialog').exists()).toBe(true)
    expect(renameCommitted(store)).toBe(false)
    await dialogBtn(wrapper, 'Stay').trigger('click') // resolve for cleanup
    expect(await pending).toBe(false)
    wrapper.unmount()
  })
})
