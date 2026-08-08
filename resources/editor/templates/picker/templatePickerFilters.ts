// Phase 26.22 — pure helpers for the Template Picker.
//
// Compose the Phase 26.21 library filters (source + category + search) and derive chip counts.
// No store, no rendering, no side effects — trivially testable and shared by the store getters.
// Everything operates on a passed snapshot of `TemplateLibraryItem`s; category and source are
// invariant KEYS (never localized).

import {
  filterLibraryBySource,
  filterLibraryByCategory,
  searchLibrary,
  type TemplateLibraryItem,
} from '../library'

export interface LibraryFilter {
  source: string | null
  category: string | null
  query: string
}

/** Apply source → category → search in that order. Null source/category = no constraint. */
export function filterLibrary(
  items: readonly TemplateLibraryItem[],
  { source, category, query }: LibraryFilter,
): TemplateLibraryItem[] {
  let out: readonly TemplateLibraryItem[] = items
  if (source) out = filterLibraryBySource(out, source)
  if (category) out = filterLibraryByCategory(out, category)
  return searchLibrary(out, query)
}

/** Count of items per category KEY (only categories with at least one item). */
export function libraryCategoryCounts(items: readonly TemplateLibraryItem[]): Record<string, number> {
  const counts: Record<string, number> = {}
  for (const item of items) counts[item.category] = (counts[item.category] ?? 0) + 1
  return counts
}

/** Count of items per source KEY (only sources with at least one item). */
export function librarySourceCounts(items: readonly TemplateLibraryItem[]): Record<string, number> {
  const counts: Record<string, number> = {}
  for (const item of items) counts[item.source] = (counts[item.source] ?? 0) + 1
  return counts
}
