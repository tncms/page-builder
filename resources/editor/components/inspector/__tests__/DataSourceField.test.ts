// PB-XREG-2 — Data-source Inspector editor. Mounted in isolation with the same
// server-published schema shape PageBuilderManager::editorMetadata() emits, and
// asserted on the plain `{ source, limit, options }` values it emits.

import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'

import { resolveFieldEditor } from '../fieldEditorRegistry'
import type { SchemaField } from '../schema'
import '../registerBuiltinFieldEditors'
import DataSourceField from '../fields/DataSourceField.vue'

function field(sources: unknown[] = SOURCES): SchemaField {
  return {
    name: 'products',
    type: 'data-source',
    label: 'Products',
    editor: {
      accepts: ['product'],
      maxItems: 24,
      sources,
      strings: { source: 'Nguồn dữ liệu', unavailable: 'Không khả dụng', none: 'Không có nguồn', items: 'Số mục' },
    },
  }
}

const SOURCES = [
  {
    id: 'ecommerce:featured-products',
    label: 'Featured products',
    recordKind: 'product',
    maxLimit: 12,
    defaultLimit: 4,
    options: [{ key: 'in_stock_only', label: 'In stock only', type: 'boolean', choices: [], default: false }],
  },
  {
    id: 'ecommerce:latest-products',
    label: 'Latest products',
    recordKind: 'product',
    maxLimit: 24,
    defaultLimit: 8,
    options: [
      {
        key: 'order',
        label: 'Order',
        type: 'enum',
        choices: [
          { value: 'newest', label: 'Newest' },
          { value: 'oldest', label: 'Oldest' },
        ],
        default: 'newest',
      },
    ],
  },
]

function lastEmit(wrapper: ReturnType<typeof mount>, event: string): unknown {
  const events = wrapper.emitted(event)
  return events ? events[events.length - 1][0] : undefined
}

describe('DataSourceField', () => {
  it('is registered for the data-source type and needs the field', () => {
    const editor = resolveFieldEditor('data-source')
    expect(editor?.component).toBe(DataSourceField)
    expect(editor?.needsField).toBe(true)
  })

  it('offers only the server-published compatible sources', () => {
    const wrapper = mount(DataSourceField, { props: { modelValue: null, field: field() } })
    const options = wrapper.findAll('[data-pb-data-source-select] option').map((o) => o.attributes('value'))
    expect(options).toEqual(['', 'ecommerce:featured-products', 'ecommerce:latest-products'])
    expect(wrapper.find('[data-pb-data-source-select]').attributes('aria-label')).toBe('Nguồn dữ liệu')
  })

  it('selecting a source emits its declared defaults and commits', async () => {
    const wrapper = mount(DataSourceField, { props: { modelValue: null, field: field() } })
    await wrapper.find('[data-pb-data-source-select]').setValue('ecommerce:latest-products')
    expect(lastEmit(wrapper, 'update')).toEqual({ source: 'ecommerce:latest-products', limit: 8, options: {} })
    expect(wrapper.emitted('commit')).toBeTruthy()
  })

  it('bounds the limit to the source ceiling and keeps options', async () => {
    const wrapper = mount(DataSourceField, {
      props: { modelValue: { source: 'ecommerce:latest-products', limit: 8, options: { order: 'oldest' } }, field: field() },
    })
    const input = wrapper.find('[data-pb-data-source-limit]')
    expect(input.attributes('max')).toBe('24')

    await input.setValue('999')
    expect(lastEmit(wrapper, 'update')).toEqual({ source: 'ecommerce:latest-products', limit: 24, options: { order: 'oldest' } })

    await input.setValue('0')
    expect((lastEmit(wrapper, 'update') as { limit: number }).limit).toBe(1)
  })

  it('edits enum and boolean options only from declared choices', async () => {
    const latest = mount(DataSourceField, { props: { modelValue: { source: 'ecommerce:latest-products', limit: 6 }, field: field() } })
    const choices = latest.findAll('[data-pb-data-source-option="order"] option').map((o) => o.attributes('value'))
    expect(choices).toEqual(['newest', 'oldest'])
    await latest.find('[data-pb-data-source-option="order"]').setValue('oldest')
    expect(lastEmit(latest, 'update')).toEqual({ source: 'ecommerce:latest-products', limit: 6, options: { order: 'oldest' } })

    const featured = mount(DataSourceField, { props: { modelValue: { source: 'ecommerce:featured-products', limit: 4 }, field: field() } })
    await featured.find('[data-pb-data-source-option="in_stock_only"]').setValue(true)
    expect(lastEmit(featured, 'update')).toEqual({ source: 'ecommerce:featured-products', limit: 4, options: { in_stock_only: true } })
  })

  it('keeps an unavailable saved source visible and never substitutes another', () => {
    const saved = { source: 'ecommerce:latest-products', limit: 8, options: { order: 'oldest' } }
    const wrapper = mount(DataSourceField, { props: { modelValue: saved, field: field([]) } })

    expect(wrapper.find('[data-pb-data-source-unavailable]').text()).toBe('Không khả dụng')
    expect((wrapper.find('[data-pb-data-source-select]').element as HTMLSelectElement).value).toBe('ecommerce:latest-products')
    expect(wrapper.find('[data-pb-data-source-limit]').exists()).toBe(false)
    expect(wrapper.emitted('update')).toBeUndefined()
  })

  it('explains when no compatible source exists', () => {
    const wrapper = mount(DataSourceField, { props: { modelValue: null, field: field([]) } })
    expect(wrapper.find('[data-pb-data-source-none]').text()).toBe('Không có nguồn')
    expect(wrapper.find('[data-pb-data-source-select]').exists()).toBe(false)
  })
})
