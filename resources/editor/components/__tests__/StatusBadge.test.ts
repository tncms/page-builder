import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import StatusBadge from '../StatusBadge.vue'

// PB-UI-1A — StatusBadge is now built on the shared PbBadge primitive. These
// guard the value/behaviour preservation of the former .pb-editor-badge: the
// status tint appears only while `on`, reactively, and the tone selects it.
describe('StatusBadge on the PbBadge primitive', () => {
  it('renders the shared badge box with the Structure status theme', () => {
    const wrapper = mount(StatusBadge, { props: { label: 'Classic', tone: 'muted' } })
    expect(wrapper.classes()).toContain('pb-ui-badge')
    expect(wrapper.classes()).toContain('pb-editor-status-badge')
    expect(wrapper.text()).toBe('Classic')
  })

  it('shows no tint when off, regardless of tone', () => {
    const wrapper = mount(StatusBadge, { props: { label: 'Draft saved', tone: 'ok', on: false } })
    expect(wrapper.classes()).not.toContain('pb-editor-status-badge--on-ok')
    expect(wrapper.classes()).not.toContain('pb-editor-status-badge--on-warn')
  })

  it('applies the tone tint only while on', () => {
    expect(mount(StatusBadge, { props: { label: 'x', tone: 'warn', on: true } }).classes())
      .toContain('pb-editor-status-badge--on-warn')
    expect(mount(StatusBadge, { props: { label: 'x', tone: 'ok', on: true } }).classes())
      .toContain('pb-editor-status-badge--on-ok')
  })

  it('reacts to the on prop toggling', async () => {
    const wrapper = mount(StatusBadge, { props: { label: 'x', tone: 'warn', on: false } })
    expect(wrapper.classes()).not.toContain('pb-editor-status-badge--on-warn')
    await wrapper.setProps({ on: true })
    expect(wrapper.classes()).toContain('pb-editor-status-badge--on-warn')
  })
})
