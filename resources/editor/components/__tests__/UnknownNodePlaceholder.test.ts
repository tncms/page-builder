import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import UnknownNodePlaceholder from '../UnknownNodePlaceholder.vue'
import { useEditorStore } from '../../stores/editor'
import type { BootData } from '../../types'

// PB-TEMPLATE-REGISTRY-2-D — the ONE shared, presentation-only unknown-component placeholder
// (Canvas + Inspector). It states the component is unavailable, shows the safe type identifier
// as ESCAPED text (no v-html sink), states the content is preserved, and uses icon + text (not
// color alone). It renders no node data and never mutates anything.

function withStrings(strings: Record<string, string> = {}) {
  setActivePinia(createPinia())
  const store = useEditorStore()
  ;(store as unknown as { boot: BootData }).boot = { strings } as BootData
  return store
}

beforeEach(() => setActivePinia(createPinia()))

describe('unknown placeholder content', () => {
  it('shows the localized heading, safe type, and preserved statement', () => {
    withStrings({
      unknownHeading: 'Component unavailable',
      unknownType: 'Component type',
      unknownPreserved: 'This content is preserved and will be saved unchanged.',
    })
    const wrapper = mount(UnknownNodePlaceholder, { props: { type: 'theme-mystery-widget', kind: 'section' } })
    const text = wrapper.text()
    expect(text).toContain('Component unavailable')
    expect(text).toContain('theme-mystery-widget')
    expect(text).toContain('This content is preserved')
  })

  it('falls back to English when no server string is present', () => {
    withStrings({})
    const wrapper = mount(UnknownNodePlaceholder, { props: { type: 'x', kind: 'block' } })
    expect(wrapper.text()).toContain('Component unavailable')
  })

  it('renders a malicious type string inert (escaped, no injection sink)', () => {
    withStrings({})
    const wrapper = mount(UnknownNodePlaceholder, {
      props: { type: '<img src=x onerror=alert(1)>', kind: 'section' },
    })
    expect(wrapper.find('img[onerror]').exists()).toBe(false)
    expect(wrapper.find('script').exists()).toBe(false)
    expect(wrapper.html()).toContain('&lt;img')
  })

  it('is an accessibly labelled note with icon + text, not color alone', () => {
    withStrings({})
    const wrapper = mount(UnknownNodePlaceholder, { props: { type: 'x', kind: 'section' } })
    const note = wrapper.get('[data-testid="unknown-placeholder"]')
    expect(note.attributes('role')).toBe('note')
    expect(note.attributes('aria-label')).toBeTruthy()
    // A non-color status cue (icon glyph) is present and hidden from AT (text conveys meaning).
    expect(wrapper.find('[aria-hidden="true"]').exists()).toBe(true)
  })

  it('shows the inspector-only note when in the inspector variant', () => {
    withStrings({ unknownInspectorNote: 'Editing controls will return when this component is available again.' })
    const wrapper = mount(UnknownNodePlaceholder, {
      props: { type: 'x', kind: 'section', variant: 'inspector' },
    })
    expect(wrapper.text()).toContain('Editing controls will return')
  })
})
