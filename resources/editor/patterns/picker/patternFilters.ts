// Phase 26.12 — pure helpers for the Pattern Picker.
//
// Category filtering + metadata-only summaries (component count / type list) derived
// from a Phase 26.11 `PatternTree`. No rendering, no store, no side effects — trivially
// testable and reused by the store getters and the card.

import type { PatternBlockSnapshot, PatternDefinition, PatternTree } from '../library'

/** Every block type in a tree, in document order (with duplicates). Sections are the
 * containers; the "components" a pattern contains are its blocks (and nested blocks). */
export function patternBlockTypes(tree: PatternTree): string[] {
  const out: string[] = []
  const walkBlock = (block: PatternBlockSnapshot): void => {
    out.push(block.type)
    const nested = block.data?.blocks
    if (Array.isArray(nested)) (nested as PatternBlockSnapshot[]).forEach(walkBlock)
  }
  for (const section of tree.sections ?? []) {
    for (const block of section.blocks ?? []) walkBlock(block)
  }
  return out
}

/** How many components (blocks) a pattern contains. */
export function patternComponentCount(pattern: PatternDefinition): number {
  return patternBlockTypes(pattern.tree).length
}

/** The distinct component types a pattern contains, in first-seen order (for the card). */
export function patternComponentSummary(pattern: PatternDefinition): string[] {
  const seen = new Set<string>()
  const summary: string[] = []
  for (const type of patternBlockTypes(pattern.tree)) {
    if (!seen.has(type)) {
      seen.add(type)
      summary.push(type)
    }
  }
  return summary
}

/** Patterns in a category, or all when `category` is null. */
export function filterByCategory(patterns: PatternDefinition[], category: string | null): PatternDefinition[] {
  if (!category) return patterns
  return patterns.filter((pattern) => pattern.category === category)
}

/** Count of patterns per category id (only categories that have at least one pattern). */
export function categoryCounts(patterns: PatternDefinition[]): Record<string, number> {
  const counts: Record<string, number> = {}
  for (const pattern of patterns) {
    const key = pattern.category
    if (key) counts[key] = (counts[key] ?? 0) + 1
  }
  return counts
}
