import { describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { mount } from '@vue/test-utils'
import InsertionMarker from '../InsertionMarker.vue'
import { useEditorStore } from '../../../stores/editor'
import type { DropResolution } from '../../../drag'
import type { GeometryRect } from '../../../geometry'

const RECT: GeometryRect = { id: 'blk2', x: 20, y: 100, left: 20, top: 100, width: 300, height: 80, right: 320, bottom: 180 }

function resolution(over: Partial<DropResolution> = {}): DropResolution {
  return {
    valid: true,
    reason: null,
    edge: 'before',
    targetId: 'blk2',
    referenceId: 'blk2',
    parentId: 'sec1',
    markerRect: RECT,
    ...over,
  }
}

function setDrag(store: ReturnType<typeof useEditorStore>, resolution: DropResolution | null) {
  store.dragActive = true
  store.dragState = { subject: { id: 'blk1', kind: 'block', type: 'text', label: 'Text', icon: null, parentId: 'sec1', index: 0 }, resolution }
}

describe('InsertionMarker', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('renders nothing when not dragging', () => {
    const wrapper = mount(InsertionMarker)
    expect(wrapper.find('[data-pb-drag-marker]').exists()).toBe(false)
  })

  it('draws a line at the top of the reference for a "before" drop', () => {
    const store = useEditorStore()
    setDrag(store, resolution({ edge: 'before' }))
    const el = mount(InsertionMarker).find('[data-pb-drag-marker]')
    expect(el.classes()).toContain('pb-drag-marker--line')
    expect(el.classes()).toContain('pb-drag-marker--valid')
    expect(el.attributes('style')).toContain('translate(20px, 100px)')
  })

  it('draws a line at the bottom for an "after" drop', () => {
    const store = useEditorStore()
    setDrag(store, resolution({ edge: 'after' }))
    const el = mount(InsertionMarker).find('[data-pb-drag-marker]')
    expect(el.attributes('style')).toContain('translate(20px, 180px)')
  })

  it('draws a filled box for an "inside" drop', () => {
    const store = useEditorStore()
    setDrag(store, resolution({ edge: 'inside' }))
    const el = mount(InsertionMarker).find('[data-pb-drag-marker]')
    expect(el.classes()).toContain('pb-drag-marker--box')
    expect(el.attributes('style')).toContain('width: 300px')
    expect(el.attributes('style')).toContain('height: 80px')
  })

  it('turns red for an invalid drop', () => {
    const store = useEditorStore()
    setDrag(store, resolution({ valid: false, reason: 'nope' }))
    const el = mount(InsertionMarker).find('[data-pb-drag-marker]')
    expect(el.classes()).toContain('pb-drag-marker--invalid')
  })
})
