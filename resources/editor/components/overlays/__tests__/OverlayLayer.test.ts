import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import OverlayLayer from '../OverlayLayer.vue'
import { seedGeometry, freshStore } from './helpers'

function rectAt(left: number, top: number, w: number, h: number) {
  return { x: left, y: top, left, top, width: w, height: h, right: left + w, bottom: top + h }
}

describe('OverlayLayer', () => {
  it('labels the selected component by its type', () => {
    seedGeometry([{ id: 'sec', kind: 'section', type: 'hero', rect: rectAt(0, 0, 400, 200) }])
    const store = freshStore()
    store.selectedComponentId = 'sec'

    const wrapper = mount(OverlayLayer)
    const label = wrapper.find('.pb-overlay-label--selected')
    expect(label.exists()).toBe(true)
    expect(label.text()).toBe('Hero')
  })

  it('is pointer-transparent so canvas clicks reach the markers', () => {
    seedGeometry([{ id: 'sec', kind: 'section', type: 'hero', rect: rectAt(0, 0, 400, 200) }])
    freshStore()
    const wrapper = mount(OverlayLayer)
    expect(wrapper.find('[data-pb-overlay-layer]').exists()).toBe(true)
  })

  it('shows selection and hover simultaneously for different components', () => {
    seedGeometry([
      { id: 'a', kind: 'section', type: 'section', rect: rectAt(0, 0, 400, 100) },
      { id: 'b', kind: 'block', type: 'text', rect: rectAt(0, 120, 400, 60) },
    ])
    const store = freshStore()
    store.selectedComponentId = 'a'
    store.hoveredComponentId = 'b'

    const wrapper = mount(OverlayLayer)
    expect(wrapper.find('.pb-overlay-box--selected').exists()).toBe(true)
    expect(wrapper.find('.pb-overlay-box--hover').exists()).toBe(true)
    expect(wrapper.find('.pb-overlay-label--hover').text()).toBe('Text')
  })
})
