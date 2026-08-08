// Phase 26.8 — default global styles.
//
// The foundation ships NO opinionated global styles: `DEFAULT_GLOBAL_STYLES` is empty
// so nothing changes appearance or leaks into the inspector (the system is additive).
// Themes/plugins populate the registry later. The shape below documents how a default
// would look — a global rule that maps a component property to a design token:
//
//   {
//     id: 'theme.body-text',
//     scope: 'global',
//     selector: 'text',                       // all `text` components
//     properties: { color: '{{color.text}}' } // resolves via the 26.7 token registry
//   }

import { GlobalStyleRegistry } from './registry'
import type { GlobalStyleRule } from './types'

/** Baseline rules seeded into a fresh registry. Empty by design (additive foundation). */
export const DEFAULT_GLOBAL_STYLES: GlobalStyleRule[] = []

/** Seed a registry with the built-in defaults (idempotent per id). */
export function seedDefaultStyles(registry: GlobalStyleRegistry, rules: GlobalStyleRule[] = DEFAULT_GLOBAL_STYLES): void {
  for (const rule of rules) registry.register(rule)
}

/** A fresh registry seeded with defaults — convenient for tests / isolated hosts. */
export function createStyleRegistry(rules: GlobalStyleRule[] = DEFAULT_GLOBAL_STYLES): GlobalStyleRegistry {
  const registry = new GlobalStyleRegistry()
  seedDefaultStyles(registry, rules)
  return registry
}
