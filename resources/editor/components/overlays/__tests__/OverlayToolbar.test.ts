import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import OverlayToolbar from '../OverlayToolbar.vue'
import { seedGeometry, freshStore } from './helpers'
import { geometryManager } from '../../../geometry'

function rectAt(left: number, top: number, w: number, h: number) {
  return { x: left, y: top, left, top, width: w, height: h, right: left + w, bottom: top + h }
}

describe('OverlayToolbar', () => {
  it('anchors to the selected component geometry', () => {
    seedGeometry([{ id: 'sec', kind: 'section', rect: rectAt(10, 20, 300, 80) }])
    const store = freshStore()
    store.selectedComponentId = 'sec'

    const wrapper = mount(OverlayToolbar)
    const bar = wrapper.find('.pb-overlay-toolbar')
    expect(bar.exists()).toBe(true)
    // Anchored to the selection's top-right (right = 310, top = 20).
    expect(bar.attributes('style') ?? '').toContain('translate(310px, 20px)')
    expect(wrapper.findAll('button').length).toBeGreaterThanOrEqual(4)
  })

  it('follows geometry when it refreshes', async () => {
    seedGeometry([{ id: 'sec', kind: 'section', rect: rectAt(10, 20, 300, 80) }])
    const store = freshStore()
    store.selectedComponentId = 'sec'

    const wrapper = mount(OverlayToolbar)
    expect(wrapper.find('.pb-overlay-toolbar').attributes('style') ?? '').toContain('translate(310px, 20px)')

    // Component moves; a geometry refresh bumps the version → overlay recomputes.
    seedGeometry([{ id: 'sec', kind: 'section', rect: rectAt(40, 100, 300, 80) }])
    store.geometryVersion = geometryManager.currentVersion()
    await nextTick()

    expect(wrapper.find('.pb-overlay-toolbar').attributes('style') ?? '').toContain('translate(340px, 100px)')
  })

  it('renders nothing without a selection', () => {
    seedGeometry([{ id: 'sec', kind: 'section', rect: rectAt(0, 0, 10, 10) }])
    const store = freshStore()
    store.selectedComponentId = null
    const wrapper = mount(OverlayToolbar)
    expect(wrapper.find('.pb-overlay-toolbar').exists()).toBe(false)
  })
})
