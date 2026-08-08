import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia, type Pinia } from 'pinia'
import BuilderStatusBar from '../BuilderStatusBar.vue'
import { useEditorStore } from '../../../../editor/stores/editor'
import { useShellStore } from '../../shellStore'
import type { LayoutContext } from '../../../../editor/types'

function layout(overrides: Partial<LayoutContext> = {}): LayoutContext {
  return {
    editor_mode: 'builder',
    draft_payload: null,
    published_payload: null,
    has_draft: true,
    has_published: true,
    is_published_current: true,
    last_saved_at: '2026-01-01T00:00:00Z',
    published_at: '2026-01-01T00:00:00Z',
    ...overrides,
  }
}

function scene(): Pinia {
  const pinia = createPinia()
  setActivePinia(pinia)
  useEditorStore().currentPayload = {
    schema: 'pagebuilder/01',
    sections: [{ type: 'hero', id: 'h1', data: {}, blocks: [] }],
  }
  return pinia
}

describe('BuilderStatusBar', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('reflects the dirty state', async () => {
    const pinia = scene()
    const wrapper = mount(BuilderStatusBar, { global: { plugins: [pinia] } })
    const editor = useEditorStore()

    expect(wrapper.find('.pb-live-builder-statusbar__status').text()).toContain('No draft yet')

    editor.dirty = true
    await wrapper.vm.$nextTick()
    const status = wrapper.find('.pb-live-builder-statusbar__status')
    expect(status.text()).toContain('Unsaved changes')
    expect(status.classes()).toContain('is-warn')
  })

  it('reads "Unpublished changes" when a saved draft is not yet published (PB-STATE-2V)', async () => {
    const pinia = scene()
    const wrapper = mount(BuilderStatusBar, { global: { plugins: [pinia] } })
    const editor = useEditorStore()

    editor.layout = layout({ is_published_current: false })
    await wrapper.vm.$nextTick()
    const status = wrapper.find('.pb-live-builder-statusbar__status')
    expect(status.text()).toContain('Unpublished changes')
    expect(status.classes()).toContain('is-warn')
  })

  it('reads "Published" only when the draft matches the published snapshot (PB-STATE-2V)', async () => {
    const pinia = scene()
    const wrapper = mount(BuilderStatusBar, { global: { plugins: [pinia] } })
    const editor = useEditorStore()

    editor.layout = layout({ is_published_current: true })
    await wrapper.vm.$nextTick()
    const status = wrapper.find('.pb-live-builder-statusbar__status')
    expect(status.text()).toContain('Published')
    expect(status.text()).not.toContain('Draft saved')
    expect(status.classes()).toContain('is-ok')
  })

  it('reads "Publish failed" after a failed publish — never a safe fallback (PB-STATE-2V)', async () => {
    const pinia = scene()
    const wrapper = mount(BuilderStatusBar, { global: { plugins: [pinia] } })
    const editor = useEditorStore()

    editor.layout = layout({ is_published_current: false })
    editor.publishFailed = true
    await wrapper.vm.$nextTick()
    const status = wrapper.find('.pb-live-builder-statusbar__status')
    expect(status.text()).toContain('Publish failed')
    expect(status.classes()).toContain('is-warn')
  })

  it('shows the selected component breadcrumb', async () => {
    const pinia = scene()
    const wrapper = mount(BuilderStatusBar, { global: { plugins: [pinia] } })
    const editor = useEditorStore()

    expect(wrapper.find('.pb-live-builder-statusbar__crumb').text()).toBe('Nothing selected')

    editor.selectedComponentId = 'h1'
    await wrapper.vm.$nextTick()
    expect(wrapper.find('.pb-live-builder-statusbar__crumb').text()).toBe('hero · #h1')
  })

  it('shows the active device label', async () => {
    const pinia = scene()
    const wrapper = mount(BuilderStatusBar, { global: { plugins: [pinia] } })
    useShellStore().setDevice('mobile')
    await wrapper.vm.$nextTick()
    expect(wrapper.find('.pb-live-builder-statusbar__device').text()).toBe('Mobile')
  })
})
