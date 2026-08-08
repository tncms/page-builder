// Phase 26.11 — Pattern Library contract (the AUTHORING side).
//
// A Pattern is a reusable component-tree SNAPSHOT — nothing more. It is NOT a new
// renderer, component system, template engine, or marketplace. The existing architecture
// (Component Registry → Component Tree → Property/Responsive/Token/Style/State values →
// Renderer) stays the single source of truth; a pattern only stores that structure and
// replays it through the existing insert pipeline.
//
// This is distinct from the server-delivered *consumption* model in `../types.ts`
// (`PatternManifest`, variables, packs, dependencies). That loads pre-authored patterns
// from the server; this captures a tree from the current editor and reuses it.
//
// The snapshot is intentionally ID-FREE: the server re-keys ids on insert, so omitting
// them is what guarantees "no duplicate component ids" on replay — no migration, no
// second component format.

/** An id-free block snapshot. Container children live in `data.blocks` (recursed). */
export interface PatternBlockSnapshot {
  type: string
  data: Record<string, unknown>
}

/** An id-free section snapshot. Its block children live in `blocks[]`. */
export interface PatternSectionSnapshot {
  type: string
  data: Record<string, unknown>
  blocks: PatternBlockSnapshot[]
}

/**
 * The reusable tree: one or more sections. Section-level mirrors the `insert-pattern`
 * command and layout presets, so replay reuses the existing pipeline verbatim.
 */
export interface PatternTree {
  sections: PatternSectionSnapshot[]
}

/**
 * A registered pattern. Intentionally small — NO marketplace metadata (pricing,
 * ownership, cloud sync, analytics), by design.
 */
export interface PatternDefinition {
  id: string
  name: string
  category?: string
  description?: string
  tree: PatternTree
}

/**
 * Foundation category vocabulary — a plain list, not a management system. Patterns may
 * use any string; these are the suggested buckets a future picker can group by.
 */
export const PATTERN_LIBRARY_CATEGORIES = ['hero', 'content', 'cta', 'gallery', 'layout'] as const

export type PatternLibraryCategory = (typeof PATTERN_LIBRARY_CATEGORIES)[number]
