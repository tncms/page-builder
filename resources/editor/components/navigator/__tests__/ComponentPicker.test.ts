import { describe, it, expect, afterEach } from 'vitest'
import { mount, type VueWrapper } from '@vue/test-utils'
import { nextTick } from 'vue'
import ComponentPicker from '../ComponentPicker.vue'
import type { DefinitionMeta } from '../../../types'

// The reusable insertion picker (Increment 6): a pure presentational chooser over
// a pre-filtered candidate list. It emits pick/close and roves the keyboard; it
// owns no candidate resolution (that lives in utils/navigatorInsert).

const candidates: DefinitionMeta[] = [
  { type: 'heading', label: 'Heading' },
  { type: 'text', label: 'Text' },
  { type: 'button', label: 'Button' },
]

let active: VueWrapper | null = null
afterEach(() => {
  active?.unmount()
  active = null
})

const items = (w: VueWrapper) => w.findAll('button[role="menuitem"]')
const focusedText = () => (document.activeElement?.textContent ?? '').trim()

function mountPicker(list = candidates) {
  const wrapper = mount(ComponentPicker, { props: { candidates: list, title: 'Add inside' }, attachTo: document.body })
  active = wrapper
  return wrapper
}

describe('ComponentPicker', () => {
  it('renders one menuitem per candidate with its label and type', () => {
    const wrapper = mountPicker()
    expect(items(wrapper).map((b) => b.find('.pb-editor-navigator-picker__label').text())).toEqual(['Heading', 'Text', 'Button'])
    expect(items(wrapper)[0].find('.pb-editor-navigator-picker__type').text()).toBe('heading')
    expect(wrapper.find('.pb-editor-navigator-picker__title').text()).toBe('Add inside')
  })

  it('emits pick with the chosen definition', async () => {
    const wrapper = mountPicker()
    await items(wrapper)[1].trigger('click')
    expect(wrapper.emitted('pick')?.[0]).toEqual([candidates[1]])
  })

  it('emits close on Escape', async () => {
    const wrapper = mountPicker()
    await wrapper.find('.pb-editor-navigator-picker').trigger('keydown', { key: 'Escape' })
    expect(wrapper.emitted('close')).toBeTruthy()
  })

  it('autofocuses the first candidate and roves with the arrows', async () => {
    const wrapper = mountPicker()
    await nextTick()
    await nextTick()
    expect(focusedText()).toContain('Heading')
    await wrapper.find('.pb-editor-navigator-picker').trigger('keydown', { key: 'ArrowDown' })
    expect(focusedText()).toContain('Text')
    await wrapper.find('.pb-editor-navigator-picker').trigger('keydown', { key: 'End' })
    expect(focusedText()).toContain('Button')
    await wrapper.find('.pb-editor-navigator-picker').trigger('keydown', { key: 'ArrowDown' }) // wraps
    expect(focusedText()).toContain('Heading')
  })

  it('shows an empty state and no menuitems when nothing can be placed', () => {
    const wrapper = mountPicker([])
    expect(items(wrapper).length).toBe(0)
    expect(wrapper.find('.pb-editor-navigator-picker__empty').exists()).toBe(true)
  })
})
