import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import SelectionOverlay from '../SelectionOverlay.vue'
import { seedGeometry, freshStore } from './helpers'

describe('SelectionOverlay', () => {
  it('draws a box at the selected component geometry', async () => {
    seedGeometry([{ id: 'sec', rect: { x: 10, y: 20, left: 10, top: 20, width: 300, height: 80, right: 310, bottom: 100 } }])
    const store = freshStore()
    store.selectedComponentId = 'sec'

    const wrapper = mount(SelectionOverlay)
    const box = wrapper.find('.pb-overlay-box--selected')
    expect(box.exists()).toBe(true)
    const style = box.attributes('style') ?? ''
    expect(style).toContain('translate(10px, 20px)')
    expect(style).toContain('width: 300px')
    expect(style).toContain('height: 80px')
  })

  it('renders nothing without a selection', () => {
    seedGeometry([{ id: 'sec', rect: { x: 0, y: 0, left: 0, top: 0, width: 10, height: 10, right: 10, bottom: 10 } }])
    const store = freshStore()
    store.selectedComponentId = null

    const wrapper = mount(SelectionOverlay)
    expect(wrapper.find('.pb-overlay-box--selected').exists()).toBe(false)
  })
})
