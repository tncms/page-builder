// Phase 26.8 — Global Style resolver.
//
// Deterministic priority chain:
//   Property Override > Component Style > Page Style > Global Style > Token Default
//
// It reuses — never duplicates — the earlier stages:
//   - responsive collapse (26.6, `resolveEffective`) runs BEFORE tokens, matching the
//     server order, so a per-breakpoint value picks the active breakpoint first;
//   - token resolution (26.7, `TokenRegistry.resolve`) turns a `{{group.name}}`
//     reference into its value (unknown/literal → unchanged).
//
// This is an editor-side resolver (for inherited-value display / previewing global
// styles). It does not change how the server renders — the PHP pipeline still owns the
// committed component value.

import type { GlobalStyleRegistry } from './registry'
import { REGISTRY_SCOPES, type StyleQuery, type StyleResolution, type StyleScope } from './types'
import { resolveEffective } from '../responsive/responsiveValue'
import type { TokenRegistry } from '../designTokens/TokenRegistry'

/** A value counts as an explicit override when it is set (undefined/null = unset). */
function isSet(value: unknown): boolean {
  return value !== undefined && value !== null
}

/**
 * Pick the winning RAW value (still possibly a token ref or responsive envelope) and
 * report where it came from. Pure — no token/breakpoint resolution here.
 */
export function resolveStyleValue(query: StyleQuery, registry: GlobalStyleRegistry): StyleResolution {
  if (isSet(query.override)) {
    return { value: query.override, source: 'property' }
  }
  for (const scope of REGISTRY_SCOPES) {
    const target = scope === 'component' ? query.componentId : query.component
    const value = registry.match(scope, target, query.property)
    if (value !== undefined) return { value, source: scope as StyleScope }
  }
  return { value: query.default, source: 'default' }
}

/** Options for finalizing a raw value into a concrete one. */
export interface FinalizeOptions {
  tokens?: TokenRegistry
  /** Active breakpoint for responsive collapse (defaults to the base). */
  breakpoint?: string
}

/**
 * Turn a raw value into a concrete one: responsive collapse first, then token
 * resolution — the same order the server uses. Both stages pass non-matching values
 * through unchanged, so a plain literal is returned untouched.
 */
export function finalizeStyleValue(raw: unknown, options: FinalizeOptions = {}): unknown {
  const collapsed = resolveEffective(raw, options.breakpoint ?? 'desktop')
  return options.tokens ? options.tokens.resolve(collapsed) : collapsed
}

/** Resolve a query all the way to a concrete value + its source. */
export function resolveStyle(
  query: StyleQuery,
  registry: GlobalStyleRegistry,
  options: FinalizeOptions = {},
): StyleResolution {
  const picked = resolveStyleValue(query, registry)
  return { value: finalizeStyleValue(picked.value, options), source: picked.source }
}
