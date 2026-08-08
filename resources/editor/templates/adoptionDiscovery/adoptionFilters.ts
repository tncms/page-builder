// Phase 26.31 — pure adoption discovery filters + counts.
//
// Every operation is a pure function over a passed snapshot — none touches a provider, registry, or
// template definition, and none mutates its input. Pack and source are matched as invariant KEYS
// (exact); search is a case-insensitive substring over name, description, and the template id.

import type { AdoptionTemplateItem } from './adoptionTemplateItem'

/** Combined discovery filter — all fields optional; a null/absent field is a no-op. */
export interface AdoptionFilter {
  pack?: string | null
  source?: string | null
  availableOnly?: boolean
  query?: string
}

/** Find one adoption item by its library-wide id. */
export function findAdoptionItem(
  items: readonly AdoptionTemplateItem[],
  id: string,
): AdoptionTemplateItem | undefined {
  return items.find((item) => item.id === id)
}

/** Items adopted through a specific pack (exact `installedFromPack` match). */
export function filterAdoptionByPack(
  items: readonly AdoptionTemplateItem[],
  packId: string,
): AdoptionTemplateItem[] {
  return items.filter((item) => item.installedFromPack === packId)
}

/** Items from a source KEY (exact match). */
export function filterAdoptionBySource(
  items: readonly AdoptionTemplateItem[],
  source: string,
): AdoptionTemplateItem[] {
  return items.filter((item) => item.source === source)
}

/** Only items that resolve in the Template Library (the availability indicator). */
export function filterAvailableAdoption(
  items: readonly AdoptionTemplateItem[],
): AdoptionTemplateItem[] {
  return items.filter((item) => item.available)
}

/**
 * Case-insensitive substring search over name, description, and template id. An empty/whitespace
 * query returns a copy of every item (a no-op filter, not a reset).
 */
export function searchAdoption(
  items: readonly AdoptionTemplateItem[],
  query: string,
): AdoptionTemplateItem[] {
  const q = query.trim().toLowerCase()
  if (!q) return items.slice()
  return items.filter((item) => {
    if ((item.name ?? '').toLowerCase().includes(q)) return true
    if ((item.description ?? '').toLowerCase().includes(q)) return true
    return item.templateId.toLowerCase().includes(q)
  })
}

/** Apply a combined filter (pack → source → available → search), each step pure and order-stable. */
export function filterAdoption(
  items: readonly AdoptionTemplateItem[],
  filter: AdoptionFilter,
): AdoptionTemplateItem[] {
  let out = items.slice()
  if (filter.pack) out = filterAdoptionByPack(out, filter.pack)
  if (filter.source) out = filterAdoptionBySource(out, filter.source)
  if (filter.availableOnly) out = filterAvailableAdoption(out)
  if (filter.query) out = searchAdoption(out, filter.query)
  return out
}

/** Count adopted templates per originating pack (for pack-relationship UI). */
export function adoptionPackCounts(items: readonly AdoptionTemplateItem[]): Record<string, number> {
  const counts: Record<string, number> = {}
  for (const item of items) {
    const pack = item.installedFromPack
    if (pack === undefined) continue
    counts[pack] = (counts[pack] ?? 0) + 1
  }
  return counts
}

/** Count adopted templates per source KEY. */
export function adoptionSourceCounts(items: readonly AdoptionTemplateItem[]): Record<string, number> {
  const counts: Record<string, number> = {}
  for (const item of items) counts[item.source] = (counts[item.source] ?? 0) + 1
  return counts
}
