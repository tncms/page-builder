import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia, type Pinia } from 'pinia'
import ComponentToolbar from '../ComponentToolbar.vue'
import { useEditorStore } from '../../../../../editor/stores/editor'
import { useInlineStore } from '../../../../inline/InlineManager'
import { useToolbarStore } from '../../../../toolbar/ToolbarManager'
import { useLiveDragStore } from '../../../../drag/LiveDragManager'
import type { ComponentGeometry } from '../../../../toolbar/types'

function geometry(componentId = 'b2'): ComponentGeometry {
  return {
    componentId,
    rect: { top: 10, left: 10, width: 200, height: 80 },
    parentId: 's2',
    kind: 'block',
    type: 'button',
  }
}

function scene(select = true): Pinia {
  const pinia = createPinia()
  setActivePinia(pinia)
  const editor = useEditorStore()
  editor.currentPayload = {
    schema: 'pagebuilder/01',
    sections: [
      { type: 'hero', id: 's1', data: {}, blocks: [] },
      {
        type: 'columns',
        id: 's2',
        data: {},
        blocks: [
          { type: 'text', id: 'b1', data: {} },
          { type: 'button', id: 'b2', data: {} },
        ],
      },
    ],
  }
  if (select) {
    editor.selectedComponentId = 'b2'
    useToolbarStore().setGeometry(geometry('b2'))
  }
  return pinia
}

function frame(): HTMLIFrameElement {
  const el = document.createElement('iframe')
  el.getBoundingClientRect = () =>
    ({ top: 100, left: 60, width: 900, height: 600, right: 960, bottom: 700, x: 60, y: 100, toJSON() {} }) as DOMRect
  return el
}

beforeEach(() => setActivePinia(createPinia()))

describe('ComponentToolbar rendering', () => {
  it('is hidden with no selection', () => {
    const pinia = scene(false)
    const wrapper = mount(ComponentToolbar, {
      props: { frameEl: frame() },
      global: { plugins: [pinia] },
    })
    expect(wrapper.find('.pb-live-toolbar').exists()).toBe(false)
  })

  it('shows the selected label, kind and primary actions', () => {
    const pinia = scene()
    const wrapper = mount(ComponentToolbar, {
      props: { frameEl: frame() },
      global: { plugins: [pinia] },
    })
    expect(wrapper.find('.pb-live-toolbar').exists()).toBe(true)
    expect(wrapper.find('.pb-live-toolbar__label-name').text()).toBe('Button')
    expect(wrapper.find('.pb-live-toolbar__label-kind').text()).toBe('Block')

    for (const label of ['Drag to move', 'Edit', 'Duplicate', 'Copy', 'Cut', 'Delete', 'More actions']) {
      expect(wrapper.find(`[aria-label="${label}"]`).exists()).toBe(true)
    }
  })

  it('is hidden while inline editing', () => {
    const pinia = scene()
    ;(useInlineStore() as unknown as { editor: unknown }).editor = {}
    const wrapper = mount(ComponentToolbar, {
      props: { frameEl: frame() },
      global: { plugins: [pinia] },
    })
    expect(wrapper.find('.pb-live-toolbar').exists()).toBe(false)
  })

  it('is hidden while a component drag is active', () => {
    const pinia = scene()
    useEditorStore().$patch({ dragActive: true })
    const wrapper = mount(ComponentToolbar, {
      props: { frameEl: frame() },
      global: { plugins: [pinia] },
    })
    expect(wrapper.find('.pb-live-toolbar').exists()).toBe(false)
  })
})

describe('ComponentToolbar actions', () => {
  it('Edit opens the Options tab', async () => {
    const pinia = scene()
    const wrapper = mount(ComponentToolbar, {
      props: { frameEl: frame() },
      global: { plugins: [pinia] },
    })
    await wrapper.get('[aria-label="Edit"]').trigger('click')
    expect(useToolbarStore().activeSidebarTab).toBe('options')
  })

  it('Duplicate dispatches exactly once', async () => {
    const pinia = scene()
    const editor = useEditorStore()
    const spy = vi.spyOn(editor, 'executeCommand').mockResolvedValue(true)
    const wrapper = mount(ComponentToolbar, {
      props: { frameEl: frame() },
      global: { plugins: [pinia] },
    })
    await wrapper.get('[aria-label="Duplicate"]').trigger('click')
    expect(spy).toHaveBeenCalledTimes(1)
    expect(spy).toHaveBeenCalledWith('duplicate-component', { component_id: 'b2' })
  })

  it('the drag handle starts a cross-iframe drag once (Phase 22.1)', async () => {
    const pinia = scene()
    const liveDrag = useLiveDragStore()
    const spy = vi.spyOn(liveDrag, 'startFromComponent').mockImplementation(() => undefined)
    const wrapper = mount(ComponentToolbar, {
      props: { frameEl: frame() },
      global: { plugins: [pinia] },
    })
    await wrapper.get('[aria-label="Drag to move"]').trigger('pointerdown')
    // beginDragSelected awaits an inline flush before handing off.
    await Promise.resolve()
    await Promise.resolve()
    expect(spy).toHaveBeenCalledTimes(1)
    expect(spy).toHaveBeenCalledWith('b2', expect.anything())
  })
})

describe('ComponentToolbar More menu', () => {
  it('opens and closes the menu', async () => {
    const pinia = scene()
    const wrapper = mount(ComponentToolbar, {
      props: { frameEl: frame() },
      global: { plugins: [pinia] },
    })
    expect(wrapper.find('.pb-live-toolbar__menu').exists()).toBe(false)

    await wrapper.get('[aria-label="More actions"]').trigger('click')
    expect(wrapper.find('.pb-live-toolbar__menu').exists()).toBe(true)

    await wrapper.get('.pb-live-toolbar__menu').trigger('keydown', { key: 'Escape' })
    expect(useToolbarStore().menuOpen).toBe(false)
  })
})
