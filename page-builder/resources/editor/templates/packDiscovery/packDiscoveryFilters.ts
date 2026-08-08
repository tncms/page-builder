// Phase 26.29 — pure discovery filters for pack items.
//
// Mirrors the Phase 26.21/26.22 template filters, but for `TemplatePackLibraryItem`. All pure,
// operating on a passed snapshot: category and source are invariant KEYS (exact match), search is
// a case-insensitive substring over name + description. No store, no side effects.

import type { TemplatePackLibraryItem } from './packLibraryItem'

export interface PackFilter {
  source: string | null
  category: string | null
  query: string
}

export function findPackItem(items: readonly TemplatePackLibraryItem[], id: string): TemplatePackLibraryItem | undefined {
  return items.find((item) => item.id === id)
}

export function filterPacksBySource(items: readonly TemplatePackLibraryItem[], source: string): TemplatePackLibraryItem[] {
  return items.filter((item) => item.source === source)
}

export function filterPacksByCategory(items: readonly TemplatePackLibraryItem[], category: string): TemplatePackLibraryItem[] {
  return items.filter((item) => item.category === category)
}

/** Case-insensitive substring over name + description. Empty query → a copy of every item. */
export function searchPacks(items: readonly TemplatePackLibraryItem[], query: string): TemplatePackLibraryItem[] {
  const q = query.trim().toLowerCase()
  if (!q) return items.slice()
  return items.filter((item) => item.name.toLowerCase().includes(q) || (item.description ?? '').toLowerCase().includes(q))
}

/** Compose source → category → search in that order. Null source/category = no constraint. */
export function filterPacks(items: readonly TemplatePackLibraryItem[], { source, category, query }: PackFilter): TemplatePackLibraryItem[] {
  let out: readonly TemplatePackLibraryItem[] = items
  if (source) out = filterPacksBySource(out, source)
  if (category) out = filterPacksByCategory(out, category)
  return searchPacks(out, query)
}

export function packCategoryCounts(items: readonly TemplatePackLibraryItem[]): Record<string, number> {
  const counts: Record<string, number> = {}
  for (const item of items) counts[item.category] = (counts[item.category] ?? 0) + 1
  return counts
}

export function packSourceCounts(items: readonly TemplatePackLibraryItem[]): Record<string, number> {
  const counts: Record<string, number> = {}
  for (const item of items) counts[item.source] = (counts[item.source] ?? 0) + 1
  return counts
}
