import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import OverlayInput from '../OverlayInput.vue'
import { useInlineStore } from '../InlineManager'
import type { InlineTarget } from '../SelectionManager'

function target(part: Partial<InlineTarget> = {}): InlineTarget {
  return {
    componentId: 'cta-1',
    type: 'cta',
    field: 'title',
    selector: '.pb-cta__title',
    multiline: false,
    translatable: true,
    value: 'Old',
    rect: { top: 10, left: 20, width: 100, height: 24 },
    typography: { fontSize: '20px', color: 'rgb(0, 0, 0)' },
    ...part,
  }
}

function mountWith(t: InlineTarget) {
  const pinia = createPinia()
  setActivePinia(pinia)
  const inline = useInlineStore()
  inline.bindSender(() => {})
  inline.open(t)
  const wrapper = mount(OverlayInput, { global: { plugins: [pinia] } })
  return { wrapper, inline }
}

describe('OverlayInput', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('renders an input for single-line fields and copies typography', () => {
    const { wrapper } = mountWith(target())
    const input = wrapper.find('input')
    expect(input.exists()).toBe(true)
    expect(wrapper.find('textarea').exists()).toBe(false)
    expect((input.element as HTMLInputElement).style.fontSize).toBe('20px')
  })

  it('renders a textarea for multiline fields', () => {
    const { wrapper } = mountWith(target({ multiline: true, value: 'a\nb' }))
    expect(wrapper.find('textarea').exists()).toBe(true)
    expect(wrapper.find('input').exists()).toBe(false)
  })

  it('streams typing to the store without committing', async () => {
    const { wrapper, inline } = mountWith(target())
    await wrapper.find('input').setValue('New')
    expect(inline.value).toBe('New')
    expect(inline.active).toBe(true)
  })

  it('commits on Enter for a single-line field', async () => {
    const { wrapper, inline } = mountWith(target())
    await wrapper.find('input').setValue('New')
    await wrapper.find('input').trigger('keydown', { key: 'Enter' })
    expect(inline.active).toBe(false)
  })

  it('inserts a newline (no commit) on plain Enter in a textarea', async () => {
    const { wrapper, inline } = mountWith(target({ multiline: true }))
    await wrapper.find('textarea').setValue('line1')
    await wrapper.find('textarea').trigger('keydown', { key: 'Enter' })
    expect(inline.active).toBe(true)
  })

  it('commits on Ctrl+Enter in a textarea', async () => {
    const { wrapper, inline } = mountWith(target({ multiline: true }))
    await wrapper.find('textarea').setValue('line1')
    await wrapper.find('textarea').trigger('keydown', { key: 'Enter', ctrlKey: true })
    expect(inline.active).toBe(false)
  })

  it('cancels on Escape', async () => {
    const { wrapper, inline } = mountWith(target())
    await wrapper.find('input').setValue('New')
    await wrapper.find('input').trigger('keydown', { key: 'Escape' })
    expect(inline.active).toBe(false)
  })

  it('commits on blur', async () => {
    const { wrapper, inline } = mountWith(target())
    await wrapper.find('input').setValue('New')
    await wrapper.find('input').trigger('blur')
    expect(inline.active).toBe(false)
  })
})
