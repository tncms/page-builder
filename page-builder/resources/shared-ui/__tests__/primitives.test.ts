import { describe, it, expect, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import PbButton from '../PbButton.vue'
import PbTabs from '../PbTabs.vue'
import PbDialog from '../PbDialog.vue'
import PbNotice from '../PbNotice.vue'
import PbBadge from '../PbBadge.vue'
import PbSpinner from '../PbSpinner.vue'

describe('PbButton', () => {
  it('is neutral/solid by default with no modifier classes', () => {
    const wrapper = mount(PbButton)
    expect(wrapper.classes()).toContain('pb-ui-btn')
    expect(wrapper.classes().some((c) => c.startsWith('pb-ui-btn--'))).toBe(false)
  })

  it('applies the tone class and emits click', async () => {
    const wrapper = mount(PbButton, { props: { tone: 'primary' } })
    expect(wrapper.classes()).toContain('pb-ui-btn--primary')
    await wrapper.trigger('click')
    expect(wrapper.emitted('click')).toHaveLength(1)
  })

  it('composes tone and emphasis (outline-danger)', () => {
    const wrapper = mount(PbButton, { props: { tone: 'danger', emphasis: 'outline' } })
    expect(wrapper.classes()).toContain('pb-ui-btn--danger')
    expect(wrapper.classes()).toContain('pb-ui-btn--outline')
  })

  it('applies the ghost emphasis', () => {
    expect(mount(PbButton, { props: { emphasis: 'ghost' } }).classes()).toContain('pb-ui-btn--ghost')
  })

  it('does not emit click when disabled and reflects the disabled attribute', async () => {
    const wrapper = mount(PbButton, { props: { disabled: true } })
    expect(wrapper.attributes('disabled')).toBeDefined()
    await wrapper.trigger('click')
    expect(wrapper.emitted('click')).toBeUndefined()
  })

  it('icon-only buttons carry an accessible label', () => {
    const wrapper = mount(PbButton, { props: { iconOnly: true, label: 'Delete' } })
    expect(wrapper.classes()).toContain('pb-ui-btn--icon')
    expect(wrapper.attributes('aria-label')).toBe('Delete')
  })
})

describe('PbTabs', () => {
  const tabs = [
    { id: 'a', label: 'A' },
    { id: 'b', label: 'B' },
    { id: 'c', label: 'C' },
  ]

  it('renders a tablist with roving tabindex on the selected tab', () => {
    const wrapper = mount(PbTabs, { props: { tabs, modelValue: 'a' } })
    expect(wrapper.attributes('role')).toBe('tablist')
    const els = wrapper.findAll('[role="tab"]')
    expect(els).toHaveLength(3)
    expect(els[0].attributes('aria-selected')).toBe('true')
    expect(els[0].attributes('tabindex')).toBe('0')
    expect(els[1].attributes('tabindex')).toBe('-1')
  })

  it('ArrowRight selects the next tab', async () => {
    const wrapper = mount(PbTabs, { props: { tabs, modelValue: 'a' }, attachTo: document.body })
    await wrapper.findAll('[role="tab"]')[0].trigger('keydown', { key: 'ArrowRight' })
    expect(wrapper.emitted('update:modelValue')?.[0]).toEqual(['b'])
    wrapper.unmount()
  })

  it('Home jumps to the first tab', async () => {
    const wrapper = mount(PbTabs, { props: { tabs, modelValue: 'c' }, attachTo: document.body })
    await wrapper.findAll('[role="tab"]')[2].trigger('keydown', { key: 'Home' })
    expect(wrapper.emitted('update:modelValue')?.[0]).toEqual(['a'])
    wrapper.unmount()
  })

  it('clicking a tab selects it', async () => {
    const wrapper = mount(PbTabs, { props: { tabs, modelValue: 'a' } })
    await wrapper.findAll('[role="tab"]')[1].trigger('click')
    expect(wrapper.emitted('update:modelValue')?.[0]).toEqual(['b'])
  })
})

describe('PbDialog', () => {
  afterEach(() => {
    document.body.innerHTML = ''
  })

  it('renders nothing while closed', () => {
    mount(PbDialog, { props: { open: false }, attachTo: document.body })
    expect(document.querySelector('[role="dialog"]')).toBeNull()
  })

  it('renders a modal dialog when open', () => {
    mount(PbDialog, { props: { open: true, title: 'Confirm' }, attachTo: document.body })
    const dialog = document.querySelector('[role="dialog"]')
    expect(dialog).not.toBeNull()
    expect(dialog?.getAttribute('aria-modal')).toBe('true')
    expect(dialog?.getAttribute('aria-label')).toBe('Confirm')
  })

  it('emits close on Escape', async () => {
    const wrapper = mount(PbDialog, { props: { open: true, title: 'Confirm' }, attachTo: document.body })
    const overlay = document.querySelector('.pb-ui-dialog__overlay') as HTMLElement
    overlay.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))
    await wrapper.vm.$nextTick()
    expect(wrapper.emitted('close')).toHaveLength(1)
  })

  it('emits close when the overlay backdrop is clicked', async () => {
    const wrapper = mount(PbDialog, { props: { open: true }, attachTo: document.body })
    const overlay = document.querySelector('.pb-ui-dialog__overlay') as HTMLElement
    overlay.dispatchEvent(new MouseEvent('click', { bubbles: true }))
    await wrapper.vm.$nextTick()
    expect(wrapper.emitted('close')).toHaveLength(1)
  })
})

describe('PbNotice', () => {
  it('announces danger/warning assertively and info/success politely', () => {
    expect(mount(PbNotice, { props: { variant: 'danger' } }).attributes('role')).toBe('alert')
    expect(mount(PbNotice, { props: { variant: 'warning' } }).attributes('role')).toBe('alert')
    expect(mount(PbNotice, { props: { variant: 'info' } }).attributes('role')).toBe('status')
    expect(mount(PbNotice, { props: { variant: 'success' } }).attributes('role')).toBe('status')
  })

  it('applies the variant class', () => {
    expect(mount(PbNotice, { props: { variant: 'success' } }).classes()).toContain('pb-ui-notice--success')
  })

  it('is subtle by default and adds the solid modifier only when asked', () => {
    expect(mount(PbNotice, { props: { variant: 'danger' } }).classes()).not.toContain('pb-ui-notice--solid')
    const solid = mount(PbNotice, { props: { variant: 'danger', emphasis: 'solid' } })
    expect(solid.classes()).toContain('pb-ui-notice--solid')
    expect(solid.attributes('role')).toBe('alert')
  })
})

describe('PbBadge', () => {
  it('renders a neutral badge with no variant modifier by default', () => {
    const wrapper = mount(PbBadge, { slots: { default: 'Draft' } })
    expect(wrapper.classes()).toContain('pb-ui-badge')
    expect(wrapper.classes().some((c) => c.startsWith('pb-ui-badge--'))).toBe(false)
    expect(wrapper.text()).toBe('Draft')
  })

  it('applies the semantic variant class', () => {
    for (const variant of ['primary', 'success', 'warning', 'danger'] as const) {
      expect(mount(PbBadge, { props: { variant } }).classes()).toContain(`pb-ui-badge--${variant}`)
    }
  })
})

describe('PbSpinner', () => {
  it('is an accessible status region with a default label', () => {
    const wrapper = mount(PbSpinner)
    expect(wrapper.classes()).toContain('pb-ui-spinner')
    expect(wrapper.attributes('role')).toBe('status')
    expect(wrapper.attributes('aria-label')).toBe('Loading…')
  })

  it('accepts a custom label', () => {
    expect(mount(PbSpinner, { props: { label: 'Saving' } }).attributes('aria-label')).toBe('Saving')
  })
})
