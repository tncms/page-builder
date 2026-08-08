// Phase 26.8 — Global Style System: the contract.
//
// A resolver LAYER, not a replacement system. A property's effective value is picked
// from a fixed priority chain and then resolved through the existing responsive (26.6)
// and token (26.7) stages — never a new value shape, never a change to how components
// store or render data. The component's own value always wins.

/** Where a value comes from, highest priority first. */
export type StyleScope = 'property' | 'component' | 'page' | 'global'

/**
 * Priority of each source (higher wins). `property` is the component's OWN stored
 * value — it is not a registered rule, it always wins when set. `default` (the schema
 * default / token default) is the base and lives below every scope.
 */
export const SCOPE_PRIORITY: Record<StyleScope | 'default', number> = {
  property: 4,
  component: 3,
  page: 2,
  global: 1,
  default: 0,
}

/** The registered scopes, in resolution (highest-first) order. Excludes `property`. */
export const REGISTRY_SCOPES: StyleScope[] = ['component', 'page', 'global']

/**
 * A shared style rule. Intentionally tiny (per the phase brief — no CSS selector
 * engine): `selector` is a component TYPE (e.g. `text`, `button`) or `*` for
 * global/page rules, and a component INSTANCE id for `component`-scoped rules.
 * `properties` maps a property name to a value — a literal, a token reference
 * (`{{color.primary}}`), or a responsive envelope (`{ desktop, tablet, … }`).
 */
export interface GlobalStyleRule {
  id: string
  scope: StyleScope
  selector: string
  properties: Record<string, unknown>
}

/** What to resolve: a component's property, plus the context needed to walk the chain. */
export interface StyleQuery {
  /** Component type, e.g. `text`. */
  component: string
  /** Property name, e.g. `color`. */
  property: string
  /** The component's OWN value for this property; `undefined`/`null` = not set. */
  override?: unknown
  /** The component instance id (matches `component`-scoped rules). */
  componentId?: string
  /** The current page id (reserved — page rules apply to the current context today). */
  pageId?: string
  /** The schema/token default — the base of the chain. */
  default?: unknown
}

/** The resolved value and where it came from. */
export interface StyleResolution {
  /** The final value after responsive + token resolution. */
  value: unknown
  /** The winning source, or `default` when nothing overrode it. */
  source: StyleScope | 'default'
}
