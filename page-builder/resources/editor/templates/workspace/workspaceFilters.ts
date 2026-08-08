// Phase 26.34 — pure workspace filters + counts.
//
// The workspace browses the existing Template Library, so filtering reuses the Phase 26.21 library
// primitives (`filterLibraryBySource` / `filterLibraryByCategory` / `searchLibrary`) rather than
// reimplementing them. All functions are pure over a passed snapshot — no provider, no mutation.

import {
  filterLibraryByCategory,
  filterLibraryBySource,
  searchLibrary,
  type TemplateLibraryItem,
} from '../library'

/** Combined workspace filter — all fields optional; a null/absent field is a no-op. */
export interface WorkspaceFilter {
  source?: string | null
  category?: string | null
  query?: string
}

/** Apply source → category → search to a snapshot (order-stable, each step pure). */
export function filterWorkspace(
  items: readonly TemplateLibraryItem[],
  filter: WorkspaceFilter,
): TemplateLibraryItem[] {
  let out = items.slice()
  if (filter.source) out = filterLibraryBySource(out, filter.source)
  if (filter.category) out = filterLibraryByCategory(out, filter.category)
  if (filter.query) out = searchLibrary(out, filter.query)
  return out
}

/** Count templates per category KEY (for filter chips). */
export function workspaceCategoryCounts(
  items: readonly TemplateLibraryItem[],
): Record<string, number> {
  const counts: Record<string, number> = {}
  for (const item of items) counts[item.category] = (counts[item.category] ?? 0) + 1
  return counts
}

/** Count templates per source KEY (for filter chips). */
export function workspaceSourceCounts(
  items: readonly TemplateLibraryItem[],
): Record<string, number> {
  const counts: Record<string, number> = {}
  for (const item of items) counts[item.source] = (counts[item.source] ?? 0) + 1
  return counts
}
