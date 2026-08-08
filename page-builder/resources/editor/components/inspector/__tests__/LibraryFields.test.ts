import { describe, it, expect, beforeEach, vi, afterEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { mount, flushPromises } from '@vue/test-utils'
import PropertyInspector from '../PropertyInspector.vue'
import { useEditorStore } from '../../../stores/editor'
import type { LayoutPayload, EditorMetadata } from '../../../types'

// Minimal schemas mirroring the built-in block library after Phase 15.1.
function schema(fields: unknown[]) {
  return { tabs: [{ name: 'content', label: 'Content', fields }] }
}

const metadata = {
  version: '1',
  sections: [{ type: 'text-section', label: 'Text Section', schema: schema([{ name: 'content', type: 'textarea', label: 'Content', translatable: true }]) }],
  blocks: [
    { type: 'heading', label: 'Heading', schema: schema([{ name: 'text', type: 'text', label: 'Text', translatable: true }, { name: 'level', type: 'select', label: 'Level', options: { h1: 'H1', h2: 'H2' } }]) },
    { type: 'text', label: 'Text', schema: schema([{ name: 'text', type: 'textarea', label: 'Text', translatable: true }]) },
    { type: 'button', label: 'Button', schema: schema([{ name: 'label', type: 'text', label: 'Label', translatable: true }, { name: 'url', type: 'url', label: 'URL' }]) },
    { type: 'image', label: 'Image', schema: schema([{ name: 'src', type: 'url', label: 'Image URL' }, { name: 'alt', type: 'text', label: 'Alt', translatable: true }]) },
    { type: 'accordion-item', label: 'Accordion', schema: schema([{ name: 'question', type: 'text', label: 'Question', translatable: true }, { name: 'answer', type: 'textarea', label: 'Answer', translatable: true }]) },
    { type: 'logo', label: 'Logo', schema: schema([{ name: 'src', type: 'url', label: 'Image URL' }, { name: 'alt', type: 'text', label: 'Alt', translatable: true }]) },
    { type: 'quote', label: 'Quote', schema: schema([{ name: 'text', type: 'textarea', label: 'Quote', translatable: true }, { name: 'author', type: 'text', label: 'Author', translatable: true }]) },
  ],
  tokens: {},
} as unknown as EditorMetadata

function payloadWith(block: { id: string; type: string; data: Record<string, unknown> }): LayoutPayload {
  return {
    version: 1,
    sections: [{ id: 's1', type: 'text-section', data: {}, blocks: [block] }],
  } as unknown as LayoutPayload
}

function setup(block: { id: string; type: string; data: Record<string, unknown> }) {
  setActivePinia(createPinia())
  const store = useEditorStore()
  store.currentPayload = payloadWith(block)
  store.metadata = metadata
  store.boot = { locale: 'vi' } as never
  const update = vi.fn(async (before: LayoutPayload, _c: string, _p: Record<string, unknown>) => ({
    payload: before,
    selection: { selected: null },
  }))
  ;(store as unknown as { client: unknown }).client = { runCommand: update }
  store.selectComponent(block.id)
  return { store, update }
}

const CASES: Array<{ type: string; fields: string[] }> = [
  { type: 'heading', fields: ['text', 'level'] },
  { type: 'text', fields: ['text'] },
  { type: 'button', fields: ['label', 'url'] },
  { type: 'image', fields: ['src', 'alt'] },
  { type: 'accordion-item', fields: ['question', 'answer'] },
  { type: 'logo', fields: ['src', 'alt'] },
  { type: 'quote', fields: ['text', 'author'] },
]

describe('PropertyInspector — built-in block fields (Phase 15.1)', () => {
  beforeEach(() => vi.useFakeTimers())
  afterEach(() => vi.useRealTimers())

  CASES.forEach(({ type, fields }) => {
    it(`renders inspector fields for the ${type} block`, () => {
      setup({ id: 'b1', type, data: {} })
      const wrapper = mount(PropertyInspector)
      for (const name of fields) {
        expect(wrapper.find(`.pb-inspector-field[data-field="${name}"]`).exists()).toBe(true)
      }
    })
  })

  it('editing a block field + Apply dispatches update-component-data', async () => {
    const { update } = setup({ id: 'b1', type: 'button', data: { label: { vi: '' }, url: '' } })
    const wrapper = mount(PropertyInspector)
    const urlInput = wrapper.find('.pb-inspector-field[data-field="url"] input')
    await urlInput.setValue('/go')
    expect(update).not.toHaveBeenCalled() // Phase 24.1: staged, not committed
    await wrapper.find('.pb-inspector__apply-btn.pb-editor-btn--primary').trigger('click')
    await flushPromises()

    expect(update).toHaveBeenCalledTimes(1)
    const [, command, params] = update.mock.calls[0]
    expect(command).toBe('update-component-data')
    expect(params.component_id).toBe('b1')
    expect((params.data as any).url).toBe('/go')
  })

  it('translatable field edits write under the current locale (on Apply)', async () => {
    const { update } = setup({ id: 'b1', type: 'heading', data: { text: { vi: '' }, level: 'h2' } })
    const wrapper = mount(PropertyInspector)
    const textInput = wrapper.find('.pb-inspector-field[data-field="text"] input')
    await textInput.setValue('Tiêu đề')
    await wrapper.find('.pb-inspector__apply-btn.pb-editor-btn--primary').trigger('click')
    await flushPromises()
    expect((update.mock.calls[0][2].data as any).text).toEqual({ vi: 'Tiêu đề' })
  })
})
