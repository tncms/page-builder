// Phase 26.33 — distribution source keys (runtime-invariant keys, NEVER localized).
//
// A distribution source identifies WHERE a template is distributed from. These keys unify the
// vocabulary used by starter packs and installation (`builtin` / `local` / `imported`). Note the
// deliberate mapping: the `local` distribution source ADAPTS the Phase 26.21 library `user` source
// — distribution reclassifies "the user's own templates" as the `local` distribution channel. Only
// UI labels derived from a source are localized (Architecture Lock #2).

/**
 * The distribution sources the registry recognizes. `builtin`, `local`, and `imported` are ACTIVE
 * (each has a concrete library-backed provider). `marketplace`, `remote`, and `cloud` are RESERVED
 * future keys — present so future distribution channels need no redesign, but no provider here.
 */
export const DISTRIBUTION_SOURCE = {
  builtin: 'builtin',
  local: 'local',
  imported: 'imported',
  // Reserved — documented seams only, intentionally unimplemented in this phase.
  marketplace: 'marketplace',
  remote: 'remote',
  cloud: 'cloud',
} as const

export type DistributionSource = (typeof DISTRIBUTION_SOURCE)[keyof typeof DISTRIBUTION_SOURCE]

/** The distribution sources with a concrete provider in this phase, in deterministic order. */
export const ACTIVE_DISTRIBUTION_SOURCES: readonly DistributionSource[] = [
  DISTRIBUTION_SOURCE.builtin,
  DISTRIBUTION_SOURCE.local,
  DISTRIBUTION_SOURCE.imported,
]

/** True when `value` is a known distribution source key (active or reserved). */
export function isKnownDistributionSource(value: string): value is DistributionSource {
  return (Object.values(DISTRIBUTION_SOURCE) as string[]).includes(value)
}
