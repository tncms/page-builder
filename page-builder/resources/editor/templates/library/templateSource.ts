// Phase 26.21 — Template source keys (runtime invariant keys, NEVER localized).
//
// A "source" identifies WHERE a template comes from. These are stable machine keys used for
// discovery, filtering, and future distribution — they are never translated. Only UI labels
// derived from a source are localized (Architecture Lock #2). See docs/template-library-foundation.md.

/**
 * The template sources the library recognizes. `builtin`, `user`, and `imported` are ACTIVE in
 * this phase. `marketplace`, `remote`, and `cloud` are RESERVED future keys — present so future
 * expansion needs no redesign, but no provider implements them here.
 */
export const TEMPLATE_SOURCE = {
  builtin: 'builtin',
  user: 'user',
  imported: 'imported',
  // Reserved — documented seams only, intentionally unimplemented in this phase.
  marketplace: 'marketplace',
  remote: 'remote',
  cloud: 'cloud',
} as const

export type TemplateSource = (typeof TEMPLATE_SOURCE)[keyof typeof TEMPLATE_SOURCE]

/** The sources with a concrete provider in this phase. */
export const ACTIVE_TEMPLATE_SOURCES: readonly TemplateSource[] = [
  TEMPLATE_SOURCE.builtin,
  TEMPLATE_SOURCE.user,
  TEMPLATE_SOURCE.imported,
]

/** True when `value` is a known source key (active or reserved). */
export function isKnownTemplateSource(value: string): value is TemplateSource {
  return (Object.values(TEMPLATE_SOURCE) as string[]).includes(value)
}
