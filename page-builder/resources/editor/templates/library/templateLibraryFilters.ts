// Phase 26.21 — Pure discovery filters + item cloning.
//
// All discovery operations are pure functions over a passed snapshot — they never touch a
// provider or mutate their input. Category and source are matched as invariant KEYS (exact);
// search is a case-insensitive substring over name, description, and tag keys.

import type { TemplateLibraryItem } from './TemplateLibraryItem'

/** Deep-clone an item so callers can never mutate stored/derived metadata. */
export function cloneLibraryItem(item: TemplateLibraryItem): TemplateLibraryItem {
  return { ...item, tags: item.tags ? [...item.tags] : undefined }
}

/** Find one item by its library-wide id. */
export function findLibraryItem(
  items: readonly TemplateLibraryItem[],
  id: string,
): TemplateLibraryItem | undefined {
  return items.find((item) => item.id === id)
}

/** Items from a source KEY (exact match). */
export function filterLibraryBySource(
  items: readonly TemplateLibraryItem[],
  source: string,
): TemplateLibraryItem[] {
  return items.filter((item) => item.source === source)
}

/** Items in a category KEY (exact match). */
export function filterLibraryByCategory(
  items: readonly TemplateLibraryItem[],
  category: string,
): TemplateLibraryItem[] {
  return items.filter((item) => item.category === category)
}

/**
 * Case-insensitive substring search over name, description, and tag keys. An empty/whitespace
 * query returns a copy of every item (search is a no-op filter, not a reset).
 */
export function searchLibrary(
  items: readonly TemplateLibraryItem[],
  query: string,
): TemplateLibraryItem[] {
  const q = query.trim().toLowerCase()
  if (!q) return items.slice()
  return items.filter((item) => {
    if (item.name.toLowerCase().includes(q)) return true
    if ((item.description ?? '').toLowerCase().includes(q)) return true
    return (item.tags ?? []).some((tag) => tag.toLowerCase().includes(q))
  })
}
