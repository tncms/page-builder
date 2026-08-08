// Phase 26.7 — Design Token Foundation.
//
// Covers the reference syntax, the registry/resolver (PHP-parity passthrough), the
// reactive `useTokens` composable, the reusable TokenPicker, and that token references
// compose with the Phase 26.6 responsive envelope as plain strings.

import { describe, it, expect } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { mount } from '@vue/test-utils'

import { parseTokenReference, isTokenReference, makeTokenReference } from '../designTokens/tokenReference'
import { TokenRegistry } from '../designTokens/TokenRegistry'
import { useTokens } from '../designTokens/useTokens'
import TokenPicker from '../designTokens/TokenPicker.vue'
import { resolveEffective } from '../responsive/responsiveValue'
import { useEditorStore } from '../../../stores/editor'
import type { EditorMetadata } from '../../../types'

const TOKENS = {
  color: { primary: '#2563eb', text: '#111827' },
  spacing: { sm: '0.5rem', lg: '1.5rem' },
  typography: { display: { family: 'Inter', weight: '700', size: '3rem' } },
}

function registry(): TokenRegistry {
  return new TokenRegistry(TOKENS)
}

// ---- Reference syntax --------------------------------------------------------

describe('token reference syntax', () => {
  it('emits the canonical wrapped form', () => {
    expect(makeTokenReference('color.primary')).toBe('{{color.primary}}')
  })

  it('parses wrapped and bare forms (and tolerates spaces)', () => {
    expect(parseTokenReference('{{color.primary}}')).toBe('color.primary')
    expect(parseTokenReference('{{ spacing.lg }}')).toBe('spacing.lg')
    expect(parseTokenReference('color.primary')).toBe('color.primary')
  })

  it('rejects non-references', () => {
    expect(parseTokenReference('#2563eb')).toBeNull()
    expect(parseTokenReference('hello world')).toBeNull()
    expect(parseTokenReference(42)).toBeNull()
    expect(parseTokenReference(null)).toBeNull()
    expect(isTokenReference('{{color.primary}}')).toBe(true)
    expect(isTokenReference('#fff')).toBe(false)
  })

  it('is shape-only: a literal like 0.5rem looks like a bare reference', () => {
    // Matches the pattern, but the registry decides if it is a REAL token.
    expect(parseTokenReference('0.5rem')).toBe('0.5rem')
  })
})

// ---- Registry + resolver (PHP parity) ---------------------------------------

describe('TokenRegistry', () => {
  it('flattens the metadata map into keyed tokens, grouped order', () => {
    const r = registry()
    expect(r.groups()).toEqual(['color', 'spacing', 'typography'])
    expect(r.byType('color').map((t) => t.key)).toEqual(['color.primary', 'color.text'])
    expect(r.get('color.primary')?.value).toBe('#2563eb')
    expect(r.get('color.primary')?.label).toBe('Primary')
    expect(r.has('color.primary')).toBe(true)
    expect(r.get('color.nope')).toBeNull()
  })

  it('resolves known references to their value', () => {
    const r = registry()
    expect(r.resolve('{{color.primary}}')).toBe('#2563eb')
    expect(r.resolve('color.primary')).toBe('#2563eb') // bare also resolves
    expect(r.resolve('{{typography.display}}')).toEqual({ family: 'Inter', weight: '700', size: '3rem' })
  })

  it('passes non-references and unknown keys through unchanged (PHP parity)', () => {
    const r = registry()
    expect(r.resolve('#ffffff')).toBe('#ffffff')
    expect(r.resolve('{{color.nope}}')).toBe('{{color.nope}}')
    expect(r.resolve('0.5rem')).toBe('0.5rem') // bare-shaped but unknown → raw
    expect(r.resolve(42)).toBe(42)
  })

  it('referencedKey only reports real, known tokens', () => {
    const r = registry()
    expect(r.referencedKey('{{color.primary}}')).toBe('color.primary')
    expect(r.referencedKey('0.5rem')).toBeNull() // shaped like a ref, but not a token
    expect(r.referencedKey('#fff')).toBeNull()
  })

  it('is empty-safe', () => {
    const r = new TokenRegistry()
    expect(r.all()).toEqual([])
    expect(r.resolve('{{color.primary}}')).toBe('{{color.primary}}')
  })
})

// ---- useTokens (reactive from store metadata) -------------------------------

describe('useTokens', () => {
  it('builds a registry from metadata.tokens and reacts to changes', () => {
    setActivePinia(createPinia())
    const store = useEditorStore()
    store.metadata = { tokens: TOKENS } as unknown as EditorMetadata
    const reg = useTokens()
    expect(reg.value.get('color.primary')?.value).toBe('#2563eb')

    store.metadata = { tokens: { color: { primary: '#000000' } } } as unknown as EditorMetadata
    expect(reg.value.get('color.primary')?.value).toBe('#000000')
  })

  it('is safe when metadata has no tokens', () => {
    setActivePinia(createPinia())
    const store = useEditorStore()
    store.metadata = {} as unknown as EditorMetadata
    expect(useTokens().value.all()).toEqual([])
  })
})

// ---- TokenPicker -------------------------------------------------------------

function mountPicker(props: Record<string, unknown>) {
  setActivePinia(createPinia())
  const store = useEditorStore()
  store.metadata = { tokens: TOKENS } as unknown as EditorMetadata
  return mount(TokenPicker, { props })
}

describe('TokenPicker', () => {
  it('lists only the requested group and renders color swatches', () => {
    const wrapper = mountPicker({ type: 'color', modelValue: '' })
    const items = wrapper.findAll('.pb-token-picker__item')
    expect(items.length).toBe(2) // color.primary + color.text only
    expect(wrapper.find('.pb-token-picker__swatch').exists()).toBe(true)
  })

  it('lists every group when no type is given', () => {
    const wrapper = mountPicker({ modelValue: '' })
    expect(wrapper.findAll('.pb-token-picker__group').length).toBe(3)
  })

  it('picking a token emits the wrapped reference and commit', async () => {
    const wrapper = mountPicker({ type: 'color', modelValue: '' })
    await wrapper.find('[data-token="color.primary"]').trigger('click')
    expect(wrapper.emitted('update')![0][0]).toBe('{{color.primary}}')
    expect(wrapper.emitted('commit')).toBeTruthy()
  })

  it('highlights the active token and offers a clear affordance', async () => {
    const wrapper = mountPicker({ type: 'color', modelValue: '{{color.primary}}' })
    expect(wrapper.find('[data-token="color.primary"]').classes()).toContain('is-active')
    const clear = wrapper.find('.pb-token-picker__clear')
    expect(clear.exists()).toBe(true)
    await clear.trigger('click')
    expect(wrapper.emitted('clear')).toBeTruthy()
  })

  it('does not highlight or offer clear for a literal value', () => {
    const wrapper = mountPicker({ type: 'color', modelValue: '#ff0000' })
    expect(wrapper.find('.pb-token-picker__item.is-active').exists()).toBe(false)
    expect(wrapper.find('.pb-token-picker__clear').exists()).toBe(false)
  })
})

// ---- Composition with responsive (Phase 26.6) -------------------------------

describe('tokens compose with responsive envelopes as plain strings', () => {
  it('a reference lives inside a breakpoint and resolves after collapse', () => {
    const env = { desktop: makeTokenReference('spacing.lg'), tablet: makeTokenReference('spacing.sm') }
    // The server resolves responsive BEFORE tokens; mirror that: collapse, then resolve.
    expect(resolveEffective(env, 'tablet')).toBe('{{spacing.sm}}')
    expect(registry().resolve(resolveEffective(env, 'tablet'))).toBe('0.5rem')
    expect(registry().resolve(resolveEffective(env, 'desktop'))).toBe('1.5rem')
  })
})
