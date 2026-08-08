// Phase 26.15 — the PatternProvider seam.
//
// One lightweight internal interface so the Pattern UI (authoring / management / picker)
// depends on a CONTRACT instead of the concrete registry. This lets a future provider
// (database, theme, plugin) slot in without touching the UI. It is deliberately NOT a
// repository / manager / service / gateway / bus — just the six registry operations the
// UI actually uses, mirroring the existing `PatternLibraryRegistry` signatures.

import type { PatternDefinition } from '../library'

export interface PatternProvider {
  /** Every registered pattern, in registration order. */
  all(): PatternDefinition[]
  /** One pattern by id, or undefined. */
  get(id: string): PatternDefinition | undefined
  /** Register (or replace by id) a pattern. */
  register(pattern: PatternDefinition): PatternDefinition
  /** Merge a patch into a pattern, preserving its id. */
  update(id: string, patch: Partial<Omit<PatternDefinition, 'id'>>): PatternDefinition | undefined
  /** Create an independent copy (fresh id, deep-cloned tree). */
  duplicate(id: string, overrides?: Partial<Omit<PatternDefinition, 'id' | 'tree'>>): PatternDefinition | undefined
  /** Remove a pattern. */
  remove(id: string): boolean
}
