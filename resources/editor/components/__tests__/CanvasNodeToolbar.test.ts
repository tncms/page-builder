// HOTFIX regression — the canvas node toolbar shared the same broken curried
// handler (`stop` returned a fn Vue discarded). A real DOM click must dispatch.
import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { mount } from '@vue/test-utils'
import CanvasNodeToolbar from '../CanvasNodeToolbar.vue'
import { useEditorStore } from '../../stores/editor'
import type { ComponentNode } from '../../types'

const node: ComponentNode = {
  id: 'blk1',
  kind: 'block',
  type: 'text',
  disabled: false,
  data: {},
  children: [],
} as unknown as ComponentNode

describe('CanvasNodeToolbar — real click dispatch (hotfix)', () => {
  let store: ReturnType<typeof useEditorStore>
  beforeEach(() => {
    setActivePinia(createPinia())
    store = useEditorStore()
  })

  it('Delete button dispatches deleteComponent with the node id', async () => {
    const spy = vi.spyOn(store, 'deleteComponent').mockResolvedValue()
    const wrapper = mount(CanvasNodeToolbar, { props: { node } })
    await wrapper.find('.pb-editor-node-action--danger').trigger('click')
    expect(spy).toHaveBeenCalledWith('blk1')
  })

  it('Duplicate button dispatches duplicateComponent with the node id', async () => {
    const spy = vi.spyOn(store, 'duplicateComponent').mockResolvedValue()
    const wrapper = mount(CanvasNodeToolbar, { props: { node } })
    await wrapper.find('[title="Duplicate"]').trigger('click')
    expect(spy).toHaveBeenCalledWith('blk1')
  })
})
