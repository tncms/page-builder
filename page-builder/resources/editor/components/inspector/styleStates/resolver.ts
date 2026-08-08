// Phase 26.10 — the state-aware resolver.
//
// Deterministic priority for a NON-normal state:
//   State + Component Override    (the component's own value for this state)
//     > State + Component Style   (state-scoped component rule)
//     > State + Page Style        (state-scoped page rule)
//     > State + Global Style      (state-scoped global rule)
//     > Normal chain              (26.8: Property > Component > Page > Global > Token Default)
//
// i.e. ANY state-specific value beats ANY normal value, and when a state has nothing
// set it degrades cleanly to the normal result — so hover looks like normal until it is
// overridden. For `normal` the function is literally the 26.8 resolver, so components
// with no state styles resolve byte-identically to today (zero regression).
//
// It reuses 26.8's `finalizeStyleValue` for every tier, so state values compose with
// the responsive engine (26.6) and design tokens (26.7) for free.

import { finalizeStyleValue, resolveStyle, type FinalizeOptions } from '../globalStyles/resolver'
import { globalStyleRegistry, type GlobalStyleRegistry } from '../globalStyles/registry'
import { REGISTRY_SCOPES, type StyleQuery } from '../globalStyles/types'
import { DEFAULT_STATE, type StateStyleResolution, type StateStyleSource, type StyleState } from './types'
import { stateStyleRegistry, type StateStyleRegistry } from './stateRegistry'

/** A value counts as an explicit override when it is set (undefined/null = unset). */
function isSet(value: unknown): boolean {
  return value !== undefined && value !== null
}

/** What to resolve: a 26.8 query plus the target state and the state-specific override. */
export interface StatefulStyleQuery extends StyleQuery {
  /** The state to resolve; defaults to `normal`. */
  state?: StyleState
  /** The component's OWN value for this property IN this state; undefined/null = unset. */
  stateOverride?: unknown
}

/** Options — the 26.8 finalize options plus the two registries (default to singletons). */
export interface ResolveStateOptions extends FinalizeOptions {
  registry?: GlobalStyleRegistry
  stateRegistry?: StateStyleRegistry
}

/** Resolve a component property, for a state, to its final value + source. */
export function resolveStatefulStyle(
  query: StatefulStyleQuery,
  options: ResolveStateOptions = {},
): StateStyleResolution {
  const state = query.state ?? DEFAULT_STATE
  const registry = options.registry ?? globalStyleRegistry
  const finalize: FinalizeOptions = { tokens: options.tokens, breakpoint: options.breakpoint }
  const base: StyleQuery = {
    component: query.component,
    property: query.property,
    override: query.override,
    componentId: query.componentId,
    pageId: query.pageId,
    default: query.default,
  }

  // Normal state = the unchanged 26.8 chain (its source is a subset of StateStyleSource).
  if (state === DEFAULT_STATE) {
    return resolveStyle(base, registry, finalize)
  }

  // 1. State + Component Override — the component's own value for this state.
  if (isSet(query.stateOverride)) {
    return { value: finalizeStyleValue(query.stateOverride, finalize), source: 'state-property' }
  }

  // 2–4. State-scoped registered rules (component > page > global), same order as 26.8.
  const stateReg = options.stateRegistry ?? stateStyleRegistry
  for (const scope of REGISTRY_SCOPES) {
    const target = scope === 'component' ? query.componentId : query.component
    const raw = stateReg.match(state, scope, target, query.property)
    if (raw !== undefined) {
      return { value: finalizeStyleValue(raw, finalize), source: `state-${scope}` as StateStyleSource }
    }
  }

  // 5. Nothing state-specific — fall back to the normal chain.
  return resolveStyle(base, registry, finalize)
}
