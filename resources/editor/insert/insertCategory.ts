// Phase 26.37 — unified insert categories (invariant KEYS, never localized).
//
// The four things a user can insert into a layout. Each maps to an EXISTING experience (a picker or
// flow) — this layer only routes to them, it never re-implements insertion. Keys are stable machine
// values used for discovery and routing; only the labels derived from them are localized.

export const INSERT_CATEGORY = {
  blocks: 'blocks',
  patterns: 'patterns',
  templates: 'templates',
  assets: 'assets',
} as const

export type InsertCategory = (typeof INSERT_CATEGORY)[keyof typeof INSERT_CATEGORY]

/** The categories in their canonical, DETERMINISTIC display order. */
export const INSERT_CATEGORY_ORDER: readonly InsertCategory[] = [
  INSERT_CATEGORY.blocks,
  INSERT_CATEGORY.patterns,
  INSERT_CATEGORY.templates,
  INSERT_CATEGORY.assets,
]

/** True when `value` is a known insert category key. */
export function isKnownInsertCategory(value: string): value is InsertCategory {
  return (Object.values(INSERT_CATEGORY) as string[]).includes(value)
}
