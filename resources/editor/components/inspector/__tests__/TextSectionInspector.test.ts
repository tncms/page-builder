import { describe, it, expect, beforeEach, vi, afterEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { mount, flushPromises } from '@vue/test-utils'
import PropertyInspector from '../PropertyInspector.vue'
import { useEditorStore } from '../../../stores/editor'
import type { LayoutPayload, EditorMetadata } from '../../../types'

// Mirrors the real text-section schema after the hotfix: a Content tab with a
// translatable `content` textarea, plus the Style tab (width/align).
const metadata = {
  version: '1',
  sections: [
    {
      type: 'text-section',
      label: 'Text Section',
      schema: {
        tabs: [
          {
            name: 'content',
            label: 'Content',
            fields: [{ name: 'content', type: 'textarea', label: 'Content', translatable: true }],
          },
          {
            name: 'style',
            label: 'Style',
            fields: [
              { name: 'width', type: 'select', label: 'Width', options: { normal: 'Normal' } },
              { name: 'align', type: 'select', label: 'Alignment', options: { left: 'Left' } },
            ],
          },
        ],
      },
    },
  ],
  blocks: [],
  tokens: {},
} as unknown as EditorMetadata

function payload(): LayoutPayload {
  return {
    version: 1,
    sections: [{ id: 'ts1', type: 'text-section', data: { width: 'normal', align: 'left', content: '' } }],
  } as unknown as LayoutPayload
}

describe('PropertyInspector — Text Section content field (hotfix)', () => {
  let store: ReturnType<typeof useEditorStore>
  let update: ReturnType<typeof vi.fn>

  beforeEach(() => {
    vi.useFakeTimers()
    setActivePinia(createPinia())
    store = useEditorStore()
    store.currentPayload = payload()
    store.metadata = metadata
    store.boot = { locale: 'vi' } as never
    update = vi.fn(async (before: LayoutPayload, _c: string, _p: Record<string, unknown>) => ({
      payload: before,
      selection: { selected: null },
    }))
    ;(store as unknown as { client: unknown }).client = { runCommand: update }
    store.selectComponent('ts1')
  })

  afterEach(() => vi.useRealTimers())

  it('renders the Content field (textarea) for a selected text section', () => {
    const wrapper = mount(PropertyInspector)
    const field = wrapper.find('.pb-inspector-field[data-field="content"]')
    expect(field.exists()).toBe(true)
    expect(field.find('textarea').exists()).toBe(true)
    // No longer only Width/Alignment.
    expect(wrapper.text()).toContain('Content')
  })

  it('commits edited content under the current locale on Apply (translatable)', async () => {
    const wrapper = mount(PropertyInspector)
    const area = wrapper.find('.pb-inspector-field[data-field="content"] textarea')
    await area.setValue('Xin chào')
    expect(update).not.toHaveBeenCalled() // Phase 24.1: staged until Apply
    await wrapper.find('.pb-inspector__apply-btn.pb-editor-btn--primary').trigger('click')
    await flushPromises()

    expect(update).toHaveBeenCalledTimes(1)
    const [, command, params] = update.mock.calls[0]
    expect(command).toBe('update-component-data')
    expect(params.component_id).toBe('ts1')
    expect((params.data as any).content).toEqual({ vi: 'Xin chào' })
  })
})
