import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import HoverOverlay from '../HoverOverlay.vue'
import { seedGeometry, freshStore } from './helpers'

const secRect = { x: 5, y: 5, left: 5, top: 5, width: 200, height: 60, right: 205, bottom: 65 }

describe('HoverOverlay', () => {
  it('draws a box at the hovered component geometry', () => {
    seedGeometry([{ id: 'sec', rect: secRect }])
    const store = freshStore()
    store.hoveredComponentId = 'sec'
    store.selectedComponentId = null

    const wrapper = mount(HoverOverlay)
    const box = wrapper.find('.pb-overlay-box--hover')
    expect(box.exists()).toBe(true)
    expect(box.attributes('style') ?? '').toContain('translate(5px, 5px)')
  })

  it('suppresses hover when it equals the selection', () => {
    seedGeometry([{ id: 'sec', rect: secRect }])
    const store = freshStore()
    store.hoveredComponentId = 'sec'
    store.selectedComponentId = 'sec'

    const wrapper = mount(HoverOverlay)
    expect(wrapper.find('.pb-overlay-box--hover').exists()).toBe(false)
  })
})
