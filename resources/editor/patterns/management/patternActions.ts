// Phase 26.14 — pure pattern management operations.
//
// Thin, UI-free wrappers over the Phase 26.11 registry (extended with update/duplicate)
// plus name validation and local search. No Vue, no picker, no editor — the store layers
// those on top. Reuses existing helpers (no duplication): `PATTERN_NAME_MAX` from the
// authoring validator, `filterByCategory` from the picker filters.

import type { PatternDefinition } from '../library'
import { patternProvider } from '../provider'
import { PATTERN_NAME_MAX } from '../authoring'
import { filterByCategory } from '../picker/patternFilters'

/** Validate a (re)name. Returns an error message, or null when valid. */
export function validatePatternName(name: string): string | null {
  const trimmed = (name ?? '').trim()
  if (!trimmed) return 'Name is required.'
  if (trimmed.length > PATTERN_NAME_MAX) return `Name must be ${PATTERN_NAME_MAX} characters or fewer.`
  return null
}

export interface RenameResult {
  ok: boolean
  error?: string
  pattern?: PatternDefinition
}

/** Rename a pattern in place — preserves its id and tree. */
export function renamePattern(id: string, name: string): RenameResult {
  const error = validatePatternName(name)
  if (error) return { ok: false, error }
  const pattern = patternProvider.update(id, { name: name.trim() })
  if (!pattern) return { ok: false, error: 'Pattern not found.' }
  return { ok: true, pattern }
}

/** Duplicate a pattern into an independent copy (fresh id, deep-cloned tree), or null. */
export function duplicatePattern(id: string): PatternDefinition | null {
  return patternProvider.duplicate(id) ?? null
}

/** Remove a pattern from the registry. Does not touch already-inserted content. */
export function deletePattern(id: string): boolean {
  return patternProvider.remove(id)
}

/**
 * Local search: category filter + a case-insensitive match across name/description/
 * category. Pure — no ranking, analytics, or recommendation (out of scope).
 */
export function searchPatterns(
  patterns: PatternDefinition[],
  query: string,
  category: string | null,
): PatternDefinition[] {
  const list = filterByCategory(patterns, category)
  const q = (query ?? '').trim().toLowerCase()
  if (!q) return list
  return list.filter((p) =>
    [p.name, p.description ?? '', p.category ?? ''].join(' ').toLowerCase().includes(q),
  )
}
