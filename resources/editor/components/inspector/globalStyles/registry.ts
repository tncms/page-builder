// Phase 26.8 — Global Style registry.
//
// Holds shared style rules and answers "what value does scope S give property P for
// this component?". Registration order is preserved so a later rule overrides an
// earlier one for the same target/property (last-registered wins) — deterministic.
//
// A module-level singleton (`globalStyleRegistry`) is the app-wide instance the
// inspector seam uses; the class is exported so tests and future hosts can build
// isolated registries.

import type { GlobalStyleRule, StyleScope } from './types'

export class GlobalStyleRegistry {
  // Insertion-ordered — Map preserves it, and re-registering an id keeps its slot but
  // updates the rule; we re-append so "last write wins" holds for overrides too.
  private rules = new Map<string, GlobalStyleRule>()

  /** Add or replace a rule by id. Re-registering moves it to the end (wins later ties). */
  register(rule: GlobalStyleRule): void {
    this.rules.delete(rule.id)
    this.rules.set(rule.id, rule)
  }

  /** Merge a partial patch into an existing rule (e.g. add/replace some properties). */
  override(id: string, patch: Partial<Omit<GlobalStyleRule, 'id'>>): void {
    const current = this.rules.get(id)
    if (!current) return
    this.register({
      ...current,
      ...patch,
      id,
      properties: { ...current.properties, ...(patch.properties ?? {}) },
    })
  }

  /** Remove a rule. */
  unregister(id: string): void {
    this.rules.delete(id)
  }

  /** A rule by id, or null. */
  get(id: string): GlobalStyleRule | null {
    return this.rules.get(id) ?? null
  }

  /** Every rule, in registration order. */
  all(): GlobalStyleRule[] {
    return [...this.rules.values()]
  }

  /** Rules of one scope, in registration order. */
  byScope(scope: StyleScope): GlobalStyleRule[] {
    return this.all().filter((r) => r.scope === scope)
  }

  /** Remove all rules (used by tests / a host reset). */
  clear(): void {
    this.rules.clear()
  }

  /**
   * The value `scope` supplies for `property`, or undefined if no rule matches.
   * `target` is a component id for `component` scope, else a component type. For
   * type-scoped rules the wildcard `*` selector matches any component. Last matching
   * rule wins.
   */
  match(scope: StyleScope, target: string | undefined, property: string): unknown {
    let value: unknown = undefined
    for (const rule of this.rules.values()) {
      if (rule.scope !== scope) continue
      if (!(property in rule.properties)) continue
      const matches = scope === 'component' ? rule.selector === target : rule.selector === target || rule.selector === '*'
      if (matches) value = rule.properties[property]
    }
    return value
  }
}

/** The app-wide registry the inspector seam resolves against. */
export const globalStyleRegistry = new GlobalStyleRegistry()
