// Phase 26.1.1 — Pure catalog helpers for the Layout popup (no Vue/DOM).
//
// Extracted so ordering, search matching and the responsive column count can be
// unit-tested directly. All metadata-driven: no hardcoded preset ids or ratios.

import type { ColumnLayoutPreset } from '../../editor/types'

/**
 * Drop duplicate presets by id (first occurrence wins). External providers may
 * register overlapping ids; the popup must render each id once.
 */
export function dedupeById(presets: ColumnLayoutPreset[]): ColumnLayoutPreset[] {
  const seen = new Set<string>()
  const out: ColumnLayoutPreset[] = []
  for (const preset of presets) {
    if (seen.has(preset.id)) continue
    seen.add(preset.id)
    out.push(preset)
  }
  return out
}

/**
 * Presets sorted by their declared `order` (ascending), preserving input order
 * for ties and for presets without an `order`. A stable, non-mutating sort.
 * Duplicate ids are dropped first (first occurrence wins).
 */
export function orderedPresets(presets: ColumnLayoutPreset[]): ColumnLayoutPreset[] {
  return dedupeById(presets)
    .map((preset, index) => ({ preset, index }))
    .sort((a, b) => {
      const oa = typeof a.preset.order === 'number' ? a.preset.order : Number.POSITIVE_INFINITY
      const ob = typeof b.preset.order === 'number' ? b.preset.order : Number.POSITIVE_INFINITY
      if (oa !== ob) return oa - ob
      return a.index - b.index
    })
    .map((entry) => entry.preset)
}

/** The searchable haystack for a preset: label + description + category + keywords. */
export function presetHaystack(preset: ColumnLayoutPreset): string {
  return [
    preset.label,
    preset.description ?? '',
    preset.category ?? '',
    ...(preset.keywords ?? []),
  ]
    .join(' ')
    .toLowerCase()
}

/** Case-insensitive substring match of `query` against the preset haystack. */
export function presetMatchesQuery(preset: ColumnLayoutPreset, query: string): boolean {
  const q = query.trim().toLowerCase()
  if (!q) return true
  return presetHaystack(preset).includes(q)
}

/**
 * Responsive column count for keyboard grid navigation. The CSS grid auto-fills,
 * so this mirrors the breakpoints (max 5) and is capped by how many cards there
 * are — a pure helper the arrow-key logic uses to jump a whole row.
 */
export function gridColumnCount(itemCount: number, maxColumns = 5): number {
  if (itemCount <= 0) return 1
  return Math.max(1, Math.min(maxColumns, itemCount))
}
