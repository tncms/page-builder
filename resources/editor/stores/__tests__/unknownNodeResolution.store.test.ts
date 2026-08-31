import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useEditorStore } from '../editor'
import type { BootData, EditorContextData } from '../../types'

// PB-TEMPLATE-REGISTRY-2-D — an UNKNOWN stored node is one whose non-empty `type` resolves to
// no component definition. Recognition is a thin projection over the ONE existing registry
// authority (definitionOf → metadata.sections/blocks); there is NO second known-type list and
// NO hardcoded built-in names. Recognition is a pure read: it never mutates the node, and
// registering the type later (metadata reactivity) flips the node back to known with no
// migration or payload rewrite (INV-R2D-15).

function unknownSection(): Record<string, unknown> {
  return {
    id: 'u-1',
    type: 'theme-mystery-widget',
    data: { headline: 'keep me', nested: { arr: [1, 'two', false, null] } },
    blocks: [{ id: 'ub-1', type: 'exotic-block', data: { note: 'nested' }, blocks: [] }],
  }
}
function knownSection(): Record<string, unknown> {
  return { id: 'k-1', type: 'hero', data: { title: 'Hi' }, blocks: [] }
}

function metadata(withMystery = false) {
  const sections = [{ type: 'hero', label: 'Hero', schema: { tabs: [] } }]
  if (withMystery) sections.push({ type: 'theme-mystery-widget', label: 'Mystery', schema: { tabs: [] } })
  return { version: '1', sections, blocks: [], tokens: {} }
}

function context(withMystery = false): EditorContextData {
  return {
    owner: { type: 'page', id: 1 },
    layout: {
      version_token: 'tok-1', is_published_current: false, has_draft: true, has_published: false,
      draft_payload: { schema: 'v1', sections: [unknownSection(), knownSection()] }, published_payload: null,
    },
    metadata: metadata(withMystery),
    permissions: { can_publish: true },
  } as unknown as EditorContextData
}

function scene(withMystery = false) {
  setActivePinia(createPinia())
  const store = useEditorStore()
  ;(store as unknown as { boot: BootData }).boot = {
    strings: { unknownHeading: 'Component unavailable', unknownBadge: 'Unavailable' },
  } as BootData
  store.applyContext(context(withMystery))
  return store
}

beforeEach(() => vi.useFakeTimers())
afterEach(() => { useEditorStore().teardownUnloadGuard?.(); vi.useRealTimers() })

describe('registry-driven known/unknown recognition', () => {
  it('resolves known vs unknown types via the existing definition authority only', () => {
    const store = scene()
    expect(store.isKnownType('section', 'hero')).toBe(true)
    expect(store.isKnownType('section', 'theme-mystery-widget')).toBe(false)
    expect(store.isKnownType('block', 'exotic-block')).toBe(false)
  })

  it('a malicious type string is inert — never executed, just unresolved', () => {
    const store = scene()
    expect(store.isKnownType('section', '<img src=x onerror=alert(1)>')).toBe(false)
    expect(store.isKnownType('block', '../../etc/passwd')).toBe(false)
  })

  it('selectedIsUnknown tracks the selected node against the registry', () => {
    const store = scene()
    store.selectComponent('k-1')
    expect(store.selectedIsUnknown).toBe(false)
    store.selectComponent('u-1')
    expect(store.selectedIsUnknown).toBe(true)
  })

  it('recognition never mutates the node payload', () => {
    const store = scene()
    const before = JSON.parse(JSON.stringify(store.currentPayload))
    store.isKnownType('section', 'theme-mystery-widget')
    store.selectComponent('u-1')
    void store.selectedIsUnknown
    expect(store.currentPayload).toEqual(before)
  })
})

describe('re-registration restores normal resolution with no migration', () => {
  it('registering the type flips the node to known while its data stays byte-identical', () => {
    const store = scene(false)
    const pristine = unknownSection()
    expect(store.isKnownType('section', 'theme-mystery-widget')).toBe(false)

    // Register the component through the normal metadata authority (reactive path).
    store.metadata = metadata(true) as never

    expect(store.isKnownType('section', 'theme-mystery-widget')).toBe(true)
    const node = store.currentPayload.sections.find((s) => (s as unknown as Record<string, unknown>).id === 'u-1')
    expect(node).toEqual(pristine) // no migration, no payload rewrite
  })
})

describe('server-resolved chrome strings', () => {
  it('returns the Admin-UI-locale string or an English fallback', () => {
    const store = scene()
    expect(store.uiString('unknownHeading', 'X')).toBe('Component unavailable')
    expect(store.uiString('missingKey', 'Fallback copy')).toBe('Fallback copy')
  })
})
