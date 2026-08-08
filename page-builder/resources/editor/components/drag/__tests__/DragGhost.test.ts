import { describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { mount } from '@vue/test-utils'
import DragGhost from '../DragGhost.vue'
import { useEditorStore } from '../../../stores/editor'

describe('DragGhost', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('renders nothing when not dragging', () => {
    expect(mount(DragGhost).find('.pb-drag-ghost').exists()).toBe(false)
  })

  it('shows the subject label and type', () => {
    const store = useEditorStore()
    store.dragActive = true
    store.dragGhost = { label: 'Hero', type: 'hero', icon: null, x: 40, y: 60 }
    const ghost = mount(DragGhost).find('.pb-drag-ghost')
    expect(ghost.text()).toContain('Hero')
    expect(ghost.text()).toContain('hero')
  })

  it('follows the pointer via a transform offset', () => {
    const store = useEditorStore()
    store.dragActive = true
    store.dragGhost = { label: 'Hero', type: 'hero', icon: null, x: 40, y: 60 }
    const ghost = mount(DragGhost).find('.pb-drag-ghost')
    // Offset by +12 from the pointer so the cursor stays visible.
    expect(ghost.attributes('style')).toContain('translate(52px, 72px)')
  })

  it('moves when the ghost position updates', async () => {
    const store = useEditorStore()
    store.dragActive = true
    store.dragGhost = { label: 'Hero', type: 'hero', icon: null, x: 0, y: 0 }
    const wrapper = mount(DragGhost)
    store.dragGhost = { ...store.dragGhost, x: 100, y: 200 }
    await wrapper.vm.$nextTick()
    expect(wrapper.find('.pb-drag-ghost').attributes('style')).toContain('translate(112px, 212px)')
  })
})
