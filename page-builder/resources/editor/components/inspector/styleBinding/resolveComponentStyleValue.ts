// Phase 26.9 — the single style-resolution entry point.
//
// One function every adopted editor calls to turn its LOCAL value into the final
// displayed value + its source. It does not re-implement anything: it maps the editor
// signature onto the Phase 26.8 resolver, which already chains
//   Property Override > Component > Page > Global > Token Default
// and finalizes through responsive collapse (26.6) + token resolution (26.7).
//
// The component's own `localValue` is the override, so an existing value ALWAYS wins —
// no migration, deterministic.

import { globalStyleRegistry, type GlobalStyleRegistry } from '../globalStyles/registry'
import { resolveStyle } from '../globalStyles/resolver'
import type { StyleResolution } from '../globalStyles/types'
import type { TokenRegistry } from '../designTokens/TokenRegistry'

export interface ComponentStyleQuery {
  /** Component type, e.g. `text`. */
  componentType: string
  /** Property name, e.g. `color`. */
  property: string
  /** The component's own value; `undefined`/`null` = not set (falls through). */
  localValue?: unknown
  /** Component instance id (matches component-scoped rules). */
  componentId?: string
  /** Current page id (reserved). */
  pageId?: string
  /** Schema/token default, the base of the chain. */
  default?: unknown
}

export interface ResolveOptions {
  /** Defaults to the app-wide singleton. */
  registry?: GlobalStyleRegistry
  tokens?: TokenRegistry
  /** Active breakpoint for responsive collapse (defaults to the base). */
  breakpoint?: string
}

/** Resolve a component property to its final value + source. */
export function resolveComponentStyleValue(query: ComponentStyleQuery, options: ResolveOptions = {}): StyleResolution {
  return resolveStyle(
    {
      component: query.componentType,
      property: query.property,
      override: query.localValue,
      componentId: query.componentId,
      pageId: query.pageId,
      default: query.default,
    },
    options.registry ?? globalStyleRegistry,
    { tokens: options.tokens, breakpoint: options.breakpoint },
  )
}
