// Phase 26.10 — Style State contract.
//
// A resolver CAPABILITY, not a new styling engine. A component property can carry
// state-specific values (hover/active/focus/disabled) that sit as an OPTIONAL layer on
// top of the existing chain (26.6 responsive → 26.7 tokens → 26.8 global styles →
// 26.9 binding). `normal` is the base and resolves byte-identically to today, so a
// component with no state styles is unaffected — no new value shape, no migration.

import type { StyleScope } from '../globalStyles/types'

/** The supported states. Extendable — add an id here and it flows through the model. */
export type StyleState = 'normal' | 'hover' | 'active' | 'focus' | 'disabled'

/** Every state, in canonical order (base first). */
export const STYLE_STATES: readonly StyleState[] = ['normal', 'hover', 'active', 'focus', 'disabled']

/** The base state — resolves through the unchanged 26.8 chain. */
export const DEFAULT_STATE: StyleState = 'normal'

/** The non-base (interactive) states, in canonical order. */
export const INTERACTIVE_STATES: readonly StyleState[] = ['hover', 'active', 'focus', 'disabled']

/** Narrow an unknown to a known state id. */
export function isStyleState(value: unknown): value is StyleState {
  return typeof value === 'string' && (STYLE_STATES as readonly string[]).includes(value)
}

/**
 * A single state's property bag — the small framework-independent unit the phase brief
 * describes. `properties` maps a property name to a value that is still a literal, a
 * token reference (`{{color.primary}}`), or a responsive envelope, exactly like 26.8.
 */
export interface StyleStateValue {
  state: StyleState
  properties: Record<string, unknown>
}

/**
 * The OPTIONAL additive storage shape: a state → property map that lives ALONGSIDE a
 * component's normal data (never replacing it). Absent/empty means the component has no
 * state styles — the backward-compatible default. `normal` is intentionally allowed but
 * unused by the resolver (the base always comes from the component's own data), so the
 * map only ever needs the interactive states.
 */
export type StyleStateMap = Partial<Record<StyleState, Record<string, unknown>>>

/**
 * Where a resolved value came from. State tiers (highest) sit above the normal 26.8
 * scopes — any state-specific value beats any normal value, so a state with nothing set
 * degrades cleanly to the normal result.
 */
export type StateStyleSource =
  | 'state-property'
  | 'state-component'
  | 'state-page'
  | 'state-global'
  | StyleScope
  | 'default'

/** A resolved value plus its winning source. Widens 26.8 `StyleResolution` with states. */
export interface StateStyleResolution {
  value: unknown
  source: StateStyleSource
}
