import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { mount, flushPromises, type VueWrapper } from '@vue/test-utils'
import { nextTick } from 'vue'
import PropertyInspector from '../../inspector/PropertyInspector.vue'
import { useEditorStore } from '../../../stores/editor'
import { useNavigatorLocksStore } from '../../../stores/navigatorLocks'
import { clonePlain } from '../../../utils/dataPath'
import type { LayoutPayload, EditorMetadata } from '../../../types'

// -------------------------------------------------------------------------
// Step 6 — the store is the SINGLE lock-guard authority every surface
// (navigator menu, canvas toolbar, keyboard shortcuts, drag) delegates to.
// -------------------------------------------------------------------------
describe('Lock guard — single store authority (Increment 4.2)', () => {
  beforeEach(() => setActivePinia(createPinia()))

  function scene() {
    const store = useEditorStore()
    store.currentPayload = {
      version: 1,
      sections: [
        {
          id: 'sec',
          type: 'hero',
          data: {},
          blocks: [
            { id: 'b1', type: 'heading', data: {} },
            { id: 'ctr', type: 'container', data: { blocks: [] } },
          ],
        },
      ],
    } as unknown as LayoutPayload
    return store
  }

  it('every structural store command is a no-op for a locked node', async () => {
    const store = scene()
    const locks = useNavigatorLocksStore()
    locks.lock(store.owner, 'ctr')
    store.copyComponent('b1') // clipboard ready so pasteInto reaches its lock check
    const spy = vi.spyOn(store, 'executeCommand').mockResolvedValue(true)

    await store.deleteComponent('ctr')
    await store.duplicateComponent('ctr')
    await store.cutComponent('ctr')
    await store.moveComponent('ctr', 'sec', 0)
    await store.pasteInto('ctr') // paste INTO a locked container
    expect(spy).not.toHaveBeenCalled()

    // An unlocked sibling still dispatches — the guard is targeted, not global.
    await store.moveComponent('b1', 'sec', 1)
    expect(spy).toHaveBeenCalledTimes(1)
    expect(spy).toHaveBeenCalledWith('move-component', { component_id: 'b1', target_parent_id: 'sec', index: 1 })
  })

  it('an unlocked node passes the guard normally', async () => {
    const store = scene()
    const spy = vi.spyOn(store, 'executeCommand').mockResolvedValue(true)
    await store.duplicateComponent('b1')
    expect(spy).toHaveBeenCalledWith('duplicate-component', { component_id: 'b1' })
  })
})

// -------------------------------------------------------------------------
// Step 7 — structural commands reach the EXISTING inspector Apply/Discard/Stay
// dialog when a draft is staged. No second dialog is created.
// -------------------------------------------------------------------------
const metadata = {
  version: '1',
  sections: [{ type: 'hero', label: 'Hero', schema: { tabs: [{ name: 'style', label: 'Style', fields: [{ name: 'align', type: 'select', label: 'Alignment', options: { left: 'Left', center: 'Center' } }] }] } }],
  blocks: [
    {
      type: 'button',
      label: 'Button',
      schema: { tabs: [{ name: 'content', label: 'Content', fields: [{ name: 'label', type: 'text', label: 'Label' }, { name: 'note', type: 'textarea', label: 'Note' }] }] },
    },
  ],
  tokens: {},
} as unknown as EditorMetadata

function findById(node: any, id: string): any {
  for (const section of node.sections ?? []) {
    if (section.id === id) return section
    for (const block of section.blocks ?? []) if (block.id === id) return block
  }
  return null
}

function inspectorScene(rejectApply = false) {
  setActivePinia(createPinia())
  const store = useEditorStore()
  store.currentPayload = {
    version: 1,
    sections: [{ id: 'sec1', type: 'hero', data: { align: 'left' }, blocks: [{ id: 'btn1', type: 'button', data: { label: { vi: 'x' }, note: '' } }] }],
  } as unknown as LayoutPayload
  store.metadata = metadata
  store.boot = { locale: 'vi' } as never
  const update = vi.fn(async (before: LayoutPayload, command: string, params: Record<string, unknown>) => {
    if (command === 'update-component-data') {
      if (rejectApply) throw new Error('apply failed')
      const next = clonePlain(before) as any
      const node = findById(next, params.component_id as string)
      if (node) node.data = params.data
      return { payload: next, selection: { selected: params.component_id } }
    }
    return { payload: before, selection: { selected: null } }
  })
  ;(store as unknown as { client: unknown }).client = { runCommand: update }
  store.selectComponent('btn1')
  return { store }
}

const dialogBtn = (w: VueWrapper, text: string) =>
  w.findAll('.pb-inspector-dialog__actions .pb-editor-btn').find((b) => b.text().includes(text))!

async function stageDraft(w: VueWrapper) {
  await w.find('.pb-inspector-field[data-field="note"] textarea').setValue('edited')
}

describe('Inspector dirty guard reached before structural commands (Increment 4.2)', () => {
  it('Apply & Continue applies the staged draft, then runs the command', async () => {
    const { store } = inspectorScene()
    const wrapper = mount(PropertyInspector)
    await stageDraft(wrapper)
    expect(store.inspectorDirty).toBe(true)

    const spy = vi.spyOn(store, 'executeCommand')
    const pending = store.duplicateComponent('btn1')
    await nextTick()
    expect(wrapper.find('.pb-inspector-dialog').exists()).toBe(true)

    await dialogBtn(wrapper, 'Apply').trigger('click')
    await flushPromises()
    await pending

    const commands = spy.mock.calls.map((c) => c[0])
    expect(commands).toContain('update-component-data') // draft applied first
    expect(commands).toContain('duplicate-component') // then command proceeds
    wrapper.unmount()
  })

  it('Discard & Continue drops the draft, then runs the command', async () => {
    const { store } = inspectorScene()
    const wrapper = mount(PropertyInspector)
    await stageDraft(wrapper)

    const spy = vi.spyOn(store, 'executeCommand')
    const pending = store.duplicateComponent('btn1')
    await nextTick()
    await dialogBtn(wrapper, 'Discard').trigger('click')
    await flushPromises()
    await pending

    const commands = spy.mock.calls.map((c) => c[0])
    expect(commands).not.toContain('update-component-data') // draft discarded, not applied
    expect(commands).toContain('duplicate-component')
    wrapper.unmount()
  })

  it('Stay Here aborts the command and keeps the draft', async () => {
    const { store } = inspectorScene()
    const wrapper = mount(PropertyInspector)
    await stageDraft(wrapper)

    const spy = vi.spyOn(store, 'executeCommand')
    void store.duplicateComponent('btn1')
    await nextTick()
    await dialogBtn(wrapper, 'Stay').trigger('click')
    await flushPromises()

    expect(spy).not.toHaveBeenCalledWith('duplicate-component', expect.anything())
    expect(store.inspectorDirty).toBe(true) // draft preserved
    wrapper.unmount()
  })

  it('a failed Apply keeps the dialog open and blocks the command', async () => {
    const { store } = inspectorScene(true) // update-component-data rejects
    const wrapper = mount(PropertyInspector)
    await stageDraft(wrapper)

    const spy = vi.spyOn(store, 'executeCommand')
    void store.duplicateComponent('btn1')
    await nextTick()
    await dialogBtn(wrapper, 'Apply').trigger('click')
    await flushPromises()

    expect(wrapper.find('.pb-inspector-dialog').exists()).toBe(true) // still open
    expect(spy).not.toHaveBeenCalledWith('duplicate-component', expect.anything())

    await dialogBtn(wrapper, 'Stay').trigger('click') // resolve the parked guard for cleanup
    wrapper.unmount()
  })
})
