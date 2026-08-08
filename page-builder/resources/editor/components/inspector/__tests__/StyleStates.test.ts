// Phase 26.10 — Style State Foundation.
//
// Covers the contract, the deterministic priority model, fallback to the normal chain,
// token + responsive composition inside states, the capability model, the storage seam,
// and — critically — that `normal` resolves byte-identically to the 26.8 resolver so
// existing components have zero regression.

import { describe, it, expect, beforeEach } from 'vitest'

import {
  STYLE_STATES,
  INTERACTIVE_STATES,
  DEFAULT_STATE,
  isStyleState,
  type StyleStateMap,
} from '../styleStates/types'
import { supportsStates, statesFor, registerStateCapableType } from '../styleStates/capabilities'
import { StateStyleRegistry } from '../styleStates/stateRegistry'
import { resolveStatefulStyle } from '../styleStates/resolver'
import { readStateProperty, setStateProperty } from '../styleStates/stateStyles'
import { GlobalStyleRegistry } from '../globalStyles/registry'
import { resolveStyle } from '../globalStyles/resolver'
import { TokenRegistry } from '../designTokens/TokenRegistry'

const TOKENS = { color: { primary: '#2563eb', text: '#111827' }, spacing: { sm: '0.5rem', lg: '1.5rem' } }
const tokens = () => new TokenRegistry(TOKENS)

// ---- Contract ----------------------------------------------------------------

describe('style state contract', () => {
  it('defines exactly the five states, base first', () => {
    expect(STYLE_STATES).toEqual(['normal', 'hover', 'active', 'focus', 'disabled'])
    expect(DEFAULT_STATE).toBe('normal')
    expect(INTERACTIVE_STATES).toEqual(['hover', 'active', 'focus', 'disabled'])
  })

  it('narrows known state ids', () => {
    expect(isStyleState('hover')).toBe(true)
    expect(isStyleState('normal')).toBe(true)
    expect(isStyleState('pressed')).toBe(false)
    expect(isStyleState(1)).toBe(false)
    expect(isStyleState(undefined)).toBe(false)
  })
})

// ---- Capability model --------------------------------------------------------

describe('capabilities', () => {
  it('enables interactive components only, not blindly', () => {
    expect(supportsStates('button')).toBe(true)
    expect(supportsStates('text')).toBe(false)
    expect(supportsStates('image')).toBe(false)
    expect(supportsStates('section')).toBe(false)
  })

  it('offers all states for a capable type, just normal otherwise', () => {
    expect(statesFor('button')).toEqual(STYLE_STATES)
    expect(statesFor('text')).toEqual(['normal'])
  })

  it('is extendable through registration', () => {
    expect(supportsStates('link-widget')).toBe(false)
    registerStateCapableType('link-widget')
    expect(supportsStates('link-widget')).toBe(true)
    expect(statesFor('link-widget')).toEqual(STYLE_STATES)
  })
})

// ---- Resolver: normal state is byte-identical to 26.8 ------------------------

describe('resolveStatefulStyle — normal state regression', () => {
  it('normal state matches the 26.8 resolver exactly (local override)', () => {
    const registry = new GlobalStyleRegistry()
    const q = { component: 'button', property: 'color', override: '#ff0000' }
    const stateful = resolveStatefulStyle({ ...q, state: 'normal' }, { registry, tokens: tokens() })
    const legacy = resolveStyle(q, registry, { tokens: tokens() })
    expect(stateful).toEqual(legacy)
    expect(stateful).toEqual({ value: '#ff0000', source: 'property' })
  })

  it('normal state resolves the normal chain (global rule) unchanged', () => {
    const registry = new GlobalStyleRegistry()
    registry.register({ id: 'g', scope: 'global', selector: 'button', properties: { color: '{{color.primary}}' } })
    const r = resolveStatefulStyle({ component: 'button', property: 'color' }, { registry, tokens: tokens() })
    expect(r).toEqual({ value: '#2563eb', source: 'global' })
  })

  it('an omitted state defaults to normal', () => {
    const registry = new GlobalStyleRegistry()
    const r = resolveStatefulStyle({ component: 'button', property: 'color', default: '#000' }, { registry })
    expect(r).toEqual({ value: '#000', source: 'default' })
  })
})

// ---- Resolver: state priority model -----------------------------------------

describe('resolveStatefulStyle — priority', () => {
  let registry: GlobalStyleRegistry
  let stateRegistry: StateStyleRegistry

  beforeEach(() => {
    registry = new GlobalStyleRegistry()
    stateRegistry = new StateStyleRegistry()
  })

  it('state override beats every lower tier', () => {
    registry.register({ id: 'n', scope: 'component', selector: 'n1', properties: { color: '#111' } })
    stateRegistry.register({ id: 'h', state: 'hover', scope: 'component', selector: 'n1', properties: { color: '#222' } })
    const r = resolveStatefulStyle(
      { component: 'button', componentId: 'n1', property: 'color', override: '#000', state: 'hover', stateOverride: '#fff' },
      { registry, stateRegistry },
    )
    expect(r).toEqual({ value: '#fff', source: 'state-property' })
  })

  it('state component style beats state page/global and the whole normal chain', () => {
    // Normal component override exists, but a hover component rule wins for hover.
    registry.register({ id: 'n', scope: 'component', selector: 'n1', properties: { color: '#111' } })
    stateRegistry.register({ id: 'hc', state: 'hover', scope: 'component', selector: 'n1', properties: { color: '#c0ffee' } })
    stateRegistry.register({ id: 'hg', state: 'hover', scope: 'global', selector: 'button', properties: { color: '#999' } })
    const r = resolveStatefulStyle(
      { component: 'button', componentId: 'n1', property: 'color', override: '#111', state: 'hover' },
      { registry, stateRegistry },
    )
    expect(r).toEqual({ value: '#c0ffee', source: 'state-component' })
  })

  it('a state global rule still beats a normal component override', () => {
    registry.register({ id: 'n', scope: 'component', selector: 'n1', properties: { color: '#111' } })
    stateRegistry.register({ id: 'hg', state: 'hover', scope: 'global', selector: 'button', properties: { color: '#abc' } })
    const r = resolveStatefulStyle(
      { component: 'button', componentId: 'n1', property: 'color', override: '#111', state: 'hover' },
      { registry, stateRegistry },
    )
    expect(r).toEqual({ value: '#abc', source: 'state-global' })
  })

  it('falls back to the normal chain when the state has nothing set', () => {
    registry.register({ id: 'n', scope: 'component', selector: 'n1', properties: { color: '#111' } })
    const r = resolveStatefulStyle(
      { component: 'button', componentId: 'n1', property: 'color', state: 'hover' },
      { registry, stateRegistry },
    )
    // hover has no value anywhere → resolves to the normal component rule.
    expect(r).toEqual({ value: '#111', source: 'component' })
  })

  it('a state override of a different property does not leak across properties', () => {
    stateRegistry.register({ id: 'h', state: 'hover', scope: 'component', selector: 'n1', properties: { background: '#222' } })
    const r = resolveStatefulStyle(
      { component: 'button', componentId: 'n1', property: 'color', default: '#333', state: 'hover' },
      { registry, stateRegistry },
    )
    expect(r).toEqual({ value: '#333', source: 'default' })
  })
})

// ---- Resolver: token + responsive composition inside states -----------------

describe('resolveStatefulStyle — token & responsive composition', () => {
  it('resolves a token reference inside a state override', () => {
    const r = resolveStatefulStyle(
      { component: 'button', property: 'color', state: 'hover', stateOverride: '{{color.primary}}' },
      { tokens: tokens(), registry: new GlobalStyleRegistry(), stateRegistry: new StateStyleRegistry() },
    )
    expect(r).toEqual({ value: '#2563eb', source: 'state-property' })
  })

  it('resolves a responsive state override by breakpoint', () => {
    const env = { desktop: '{{spacing.lg}}', mobile: '{{spacing.sm}}' }
    const opts = { tokens: tokens(), registry: new GlobalStyleRegistry(), stateRegistry: new StateStyleRegistry() }
    expect(
      resolveStatefulStyle({ component: 'button', property: 'pad', state: 'hover', stateOverride: env }, { ...opts, breakpoint: 'mobile' }).value,
    ).toBe('0.5rem')
    expect(
      resolveStatefulStyle({ component: 'button', property: 'pad', state: 'hover', stateOverride: env }, { ...opts, breakpoint: 'desktop' }).value,
    ).toBe('1.5rem')
  })

  it('resolves a token inside a state-scoped registered rule', () => {
    const stateRegistry = new StateStyleRegistry()
    stateRegistry.register({ id: 'h', state: 'hover', scope: 'global', selector: 'button', properties: { color: '{{color.text}}' } })
    const r = resolveStatefulStyle(
      { component: 'button', property: 'color', state: 'hover' },
      { tokens: tokens(), registry: new GlobalStyleRegistry(), stateRegistry },
    )
    expect(r).toEqual({ value: '#111827', source: 'state-global' })
  })
})

// ---- State registry ----------------------------------------------------------

describe('StateStyleRegistry', () => {
  it('partitions rules by state', () => {
    const reg = new StateStyleRegistry()
    reg.register({ id: 'h', state: 'hover', scope: 'component', selector: 'n1', properties: { color: '#h' } })
    reg.register({ id: 'f', state: 'focus', scope: 'component', selector: 'n1', properties: { color: '#f' } })
    expect(reg.match('hover', 'component', 'n1', 'color')).toBe('#h')
    expect(reg.match('focus', 'component', 'n1', 'color')).toBe('#f')
    expect(reg.match('active', 'component', 'n1', 'color')).toBeUndefined()
  })

  it('unregisters and clears', () => {
    const reg = new StateStyleRegistry()
    reg.register({ id: 'h', state: 'hover', scope: 'global', selector: 'button', properties: { color: '#h' } })
    reg.unregister('hover', 'h')
    expect(reg.match('hover', 'global', 'button', 'color')).toBeUndefined()
    reg.register({ id: 'h2', state: 'hover', scope: 'global', selector: 'button', properties: { color: '#h2' } })
    reg.clear()
    expect(reg.match('hover', 'global', 'button', 'color')).toBeUndefined()
  })
})

// ---- Storage seam (future-UI, no migration) ---------------------------------

describe('state storage helpers', () => {
  it('reads a state property, undefined when unset', () => {
    const states: StyleStateMap = { hover: { color: '#fff' } }
    expect(readStateProperty(states, 'hover', 'color')).toBe('#fff')
    expect(readStateProperty(states, 'hover', 'background')).toBeUndefined()
    expect(readStateProperty(states, 'focus', 'color')).toBeUndefined()
    expect(readStateProperty(undefined, 'hover', 'color')).toBeUndefined()
  })

  it('sets immutably, returning a new map', () => {
    const before: StyleStateMap = { hover: { color: '#111' } }
    const after = setStateProperty(before, 'hover', 'color', '#222')
    expect(after).toEqual({ hover: { color: '#222' } })
    expect(before).toEqual({ hover: { color: '#111' } }) // unchanged
    expect(after).not.toBe(before)
  })

  it('creates a state bag on first write from an absent map', () => {
    expect(setStateProperty(undefined, 'focus', 'color', '#0f0')).toEqual({ focus: { color: '#0f0' } })
  })

  it('clears a property and collapses an emptied bag back out of the map', () => {
    const before: StyleStateMap = { hover: { color: '#111', background: '#222' } }
    expect(setStateProperty(before, 'hover', 'color', null)).toEqual({ hover: { background: '#222' } })
    // clearing the last override drops the state entirely (no orphan keys)
    expect(setStateProperty({ hover: { color: '#111' } }, 'hover', 'color', undefined)).toEqual({})
  })
})
