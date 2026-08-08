import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia, type Pinia } from 'pinia'
import BoxModelOverlay from '../BoxModelOverlay.vue'
import { useBoxModelStore } from '../BoxModelManager'
import { useEditorStore } from '../../../editor/stores/editor'
import type { BoxMetrics } from '../types'

function metrics(): BoxMetrics {
  return {
    componentId: 'hero-1',
    margin: { top: 10, right: 10, bottom: 10, left: 10 },
    padding: { top: 5, right: 5, bottom: 5, left: 5 },
    border: { top: 0, right: 0, bottom: 0, left: 0 },
    rect: { top: 0, left: 0, width: 200, height: 100 },
  }
}

function scene(type: string): Pinia {
  const pinia = createPinia()
  setActivePinia(pinia)
  const editor = useEditorStore()
  editor.currentPayload = {
    schema: 'pagebuilder/01',
    sections: [{ type, id: 'hero-1', data: {}, blocks: [] }],
  }
  editor.selectedComponentId = 'hero-1'
  useBoxModelStore().setMetrics(metrics())
  return pinia
}

describe('BoxModelOverlay', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('renders the three regions and eight handles for a supported component', () => {
    const pinia = scene('hero')
    const wrapper = mount(BoxModelOverlay, { global: { plugins: [pinia] } })

    expect(wrapper.find('.pb-box-overlay').exists()).toBe(true)
    expect(wrapper.find('.pb-box-overlay__margin').exists()).toBe(true)
    expect(wrapper.find('.pb-box-overlay__padding').exists()).toBe(true)
    expect(wrapper.find('.pb-box-overlay__content').exists()).toBe(true)
    expect(wrapper.findAll('.pb-box-overlay__handle')).toHaveLength(8)
    expect(wrapper.findAll('.pb-box-overlay__value')).toHaveLength(8)
  })

  it('is hidden for an unsupported component', () => {
    const pinia = scene('testimonial')
    const wrapper = mount(BoxModelOverlay, { global: { plugins: [pinia] } })
    expect(wrapper.find('.pb-box-overlay').exists()).toBe(false)
  })
})
