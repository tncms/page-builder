// Phase 26.8 — Global Style System Foundation.
//
// Covers the registry, the deterministic priority resolver, integration with tokens
// (26.7) and responsive (26.6), the empty additive defaults, and the inspector seam.
// Nothing here modifies editors/preview/commit — the foundation is additive.

import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

import { GlobalStyleRegistry, globalStyleRegistry } from '../globalStyles/registry'
import { resolveStyleValue, finalizeStyleValue, resolveStyle } from '../globalStyles/resolver'
import { DEFAULT_GLOBAL_STYLES, createStyleRegistry } from '../globalStyles/defaults'
import { SCOPE_PRIORITY } from '../globalStyles/types'
import { useGlobalStyles } from '../globalStyles/useGlobalStyles'
import { TokenRegistry } from '../designTokens/TokenRegistry'
import { setActiveBreakpoint } from '../responsive/activeBreakpoint'
import { useEditorStore } from '../../../stores/editor'
import type { EditorMetadata } from '../../../types'

const TOKENS = { color: { primary: '#2563eb', text: '#111827' }, spacing: { sm: '0.5rem', lg: '1.5rem' } }
const tokens = () => new TokenRegistry(TOKENS)

function reg(rules: Parameters<GlobalStyleRegistry['register']>[0][] = []): GlobalStyleRegistry {
  const r = new GlobalStyleRegistry()
  for (const rule of rules) r.register(rule)
  return r
}

// ---- Registry ----------------------------------------------------------------

describe('GlobalStyleRegistry', () => {
  it('registers, retrieves, scopes, overrides, and unregisters', () => {
    const r = reg()
    r.register({ id: 'a', scope: 'global', selector: 'text', properties: { color: 'red' } })
    expect(r.get('a')?.properties.color).toBe('red')
    expect(r.byScope('global').length).toBe(1)

    r.override('a', { properties: { color: 'blue', size: '2rem' } })
    expect(r.get('a')?.properties).toEqual({ color: 'blue', size: '2rem' })

    r.unregister('a')
    expect(r.get('a')).toBeNull()
    expect(r.all()).toEqual([])
  })

  it('matches by component type, wildcard, and instance id; last wins', () => {
    const r = reg([
      { id: 'g1', scope: 'global', selector: '*', properties: { color: 'wild' } },
      { id: 'g2', scope: 'global', selector: 'text', properties: { color: 'typed' } },
      { id: 'c1', scope: 'component', selector: 'node-1', properties: { color: 'instance' } },
    ])
    expect(r.match('global', 'text', 'color')).toBe('typed') // typed rule registered last, wins over '*'
    expect(r.match('global', 'button', 'color')).toBe('wild') // only '*' matches button
    expect(r.match('component', 'node-1', 'color')).toBe('instance')
    expect(r.match('component', 'node-2', 'color')).toBeUndefined()
    expect(r.match('global', 'text', 'missing')).toBeUndefined()
  })
})

// ---- Priority resolver -------------------------------------------------------

describe('resolveStyleValue — priority chain', () => {
  const rules = [
    { id: 'g', scope: 'global' as const, selector: 'text', properties: { color: 'GLOBAL' } },
    { id: 'p', scope: 'page' as const, selector: 'text', properties: { color: 'PAGE' } },
    { id: 'c', scope: 'component' as const, selector: 'node-1', properties: { color: 'COMPONENT' } },
  ]

  it('honours Property > Component > Page > Global > Default', () => {
    const r = reg(rules)
    const base = { component: 'text', property: 'color', componentId: 'node-1', default: 'DEFAULT' }

    expect(resolveStyleValue({ ...base, override: 'OWN' }, r)).toEqual({ value: 'OWN', source: 'property' })
    expect(resolveStyleValue(base, r)).toEqual({ value: 'COMPONENT', source: 'component' })
    expect(resolveStyleValue({ ...base, componentId: 'other' }, r)).toEqual({ value: 'PAGE', source: 'page' })

    const r2 = reg([rules[0]]) // global only
    expect(resolveStyleValue(base, r2)).toEqual({ value: 'GLOBAL', source: 'global' })
    expect(resolveStyleValue(base, reg())).toEqual({ value: 'DEFAULT', source: 'default' })
  })

  it('treats undefined/null override as unset, but keeps an explicit empty string', () => {
    const r = reg([rules[0]])
    const base = { component: 'text', property: 'color', default: 'DEFAULT' }
    expect(resolveStyleValue({ ...base, override: undefined }, r).source).toBe('global')
    expect(resolveStyleValue({ ...base, override: null }, r).source).toBe('global')
    expect(resolveStyleValue({ ...base, override: '' }, r)).toEqual({ value: '', source: 'property' })
  })

  it('priority constants agree with the chain', () => {
    expect(SCOPE_PRIORITY.property).toBeGreaterThan(SCOPE_PRIORITY.component)
    expect(SCOPE_PRIORITY.component).toBeGreaterThan(SCOPE_PRIORITY.page)
    expect(SCOPE_PRIORITY.page).toBeGreaterThan(SCOPE_PRIORITY.global)
    expect(SCOPE_PRIORITY.global).toBeGreaterThan(SCOPE_PRIORITY.default)
  })
})

// ---- Finalization: reuse of tokens (26.7) + responsive (26.6) ----------------

describe('finalizeStyleValue', () => {
  it('passes a literal through unchanged', () => {
    expect(finalizeStyleValue('#ffffff', { tokens: tokens() })).toBe('#ffffff')
  })

  it('resolves a token reference', () => {
    expect(finalizeStyleValue('{{color.primary}}', { tokens: tokens() })).toBe('#2563eb')
  })

  it('collapses a responsive envelope by breakpoint, then resolves the token', () => {
    const env = { desktop: '{{spacing.lg}}', mobile: '{{spacing.sm}}' }
    expect(finalizeStyleValue(env, { tokens: tokens(), breakpoint: 'desktop' })).toBe('1.5rem')
    expect(finalizeStyleValue(env, { tokens: tokens(), breakpoint: 'mobile' })).toBe('0.5rem')
  })

  it('is safe with no token registry', () => {
    expect(finalizeStyleValue('{{color.primary}}')).toBe('{{color.primary}}') // unresolved → raw
  })
})

describe('resolveStyle — end to end', () => {
  it('picks by priority then finalizes tokens + breakpoint', () => {
    const r = reg([{ id: 'g', scope: 'global', selector: 'text', properties: { color: '{{color.primary}}' } }])
    const res = resolveStyle({ component: 'text', property: 'color' }, r, { tokens: tokens() })
    expect(res).toEqual({ value: '#2563eb', source: 'global' })
  })

  it('a component override that is a token/responsive value is still finalized', () => {
    const res = resolveStyle(
      { component: 'text', property: 'pad', override: { desktop: '{{spacing.lg}}' } },
      reg(),
      { tokens: tokens(), breakpoint: 'desktop' },
    )
    expect(res).toEqual({ value: '1.5rem', source: 'property' })
  })
})

// ---- Defaults (additive, no imposed styling) ---------------------------------

describe('defaults', () => {
  it('ship no opinionated global styles', () => {
    expect(DEFAULT_GLOBAL_STYLES).toEqual([])
    expect(createStyleRegistry().all()).toEqual([])
  })

  it('the app-wide singleton starts empty (nothing leaks into the inspector)', () => {
    expect(globalStyleRegistry.all()).toEqual([])
  })
})

// ---- Inspector seam ----------------------------------------------------------

describe('useGlobalStyles seam', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    const store = useEditorStore()
    store.metadata = { tokens: TOKENS } as unknown as EditorMetadata
    globalStyleRegistry.clear()
    setActiveBreakpoint('desktop')
  })
  afterEach(() => {
    globalStyleRegistry.clear()
    setActiveBreakpoint('desktop')
  })

  it('resolves against live tokens and the active breakpoint', () => {
    const { registry, resolve } = useGlobalStyles()
    registry.register({
      id: 'body',
      scope: 'global',
      selector: 'text',
      properties: { color: { desktop: '{{color.primary}}', mobile: '{{color.text}}' } },
    })

    expect(resolve({ component: 'text', property: 'color' })).toEqual({ value: '#2563eb', source: 'global' })

    setActiveBreakpoint('mobile')
    expect(resolve({ component: 'text', property: 'color' })).toEqual({ value: '#111827', source: 'global' })
  })

  it('a component own value still wins over a global rule', () => {
    const { registry, resolve } = useGlobalStyles()
    registry.register({ id: 'body', scope: 'global', selector: 'text', properties: { color: '{{color.primary}}' } })
    expect(resolve({ component: 'text', property: 'color', override: '#ff0000' })).toEqual({
      value: '#ff0000',
      source: 'property',
    })
  })
})
