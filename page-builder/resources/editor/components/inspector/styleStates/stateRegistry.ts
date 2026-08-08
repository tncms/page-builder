// Phase 26.10 — state-scoped style rules ("State + Component Style" tier).
//
// This is NOT a new engine: it reuses the 26.8 `GlobalStyleRegistry` verbatim, one
// instance per state, so a state's component/page/global rules match with the exact same
// specificity semantics as normal rules. Partitioning by state keeps 26.8 untouched — no
// change to `GlobalStyleRule`, no change to the global registry's `match`.

import { GlobalStyleRegistry } from '../globalStyles/registry'
import type { GlobalStyleRule, StyleScope } from '../globalStyles/types'
import type { StyleState } from './types'

/** A 26.8 style rule tagged with the state it applies to. */
export interface StateStyleRule extends GlobalStyleRule {
  state: StyleState
}

/** A per-state view over `GlobalStyleRegistry` instances. */
export class StateStyleRegistry {
  private byState = new Map<StyleState, GlobalStyleRegistry>()

  private registryFor(state: StyleState): GlobalStyleRegistry {
    let registry = this.byState.get(state)
    if (!registry) {
      registry = new GlobalStyleRegistry()
      this.byState.set(state, registry)
    }
    return registry
  }

  /** Register a state-scoped rule (creates the state's sub-registry on first use). */
  register(rule: StateStyleRule): void {
    this.registryFor(rule.state).register({
      id: rule.id,
      scope: rule.scope,
      selector: rule.selector,
      properties: rule.properties,
    })
  }

  /** Remove a rule from a state's sub-registry. */
  unregister(state: StyleState, id: string): void {
    this.byState.get(state)?.unregister(id)
  }

  /** Match a property within a state; `undefined` when the state has no such rule. */
  match(state: StyleState, scope: StyleScope, target: string | undefined, property: string): unknown {
    return this.byState.get(state)?.match(scope, target, property)
  }

  /** Drop every state's rules. */
  clear(): void {
    this.byState.clear()
  }
}

/** App-wide singleton (mirrors `globalStyleRegistry`). */
export const stateStyleRegistry = new StateStyleRegistry()
