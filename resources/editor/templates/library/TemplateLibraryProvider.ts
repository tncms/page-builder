// Phase 26.21 — TemplateLibraryProvider contract (discovery, NOT runtime).
//
// This sits ABOVE `TemplateProvider` (get/register/update/remove). Responsibilities are strictly
// DISCOVERY: browse, search, filter, categorize. It never mutates templates and never resolves
// or applies layout trees — those remain with TemplateProvider and the Template Runtime. Keeping
// the two boundaries separate is deliberate; do not merge them.

import type { TemplateLibraryItem } from './TemplateLibraryItem'

export interface TemplateLibraryProvider {
  /** Every discoverable item, as immutable metadata snapshots. */
  all(): TemplateLibraryItem[]
  /** One item by its library-wide id, or undefined. */
  find(id: string): TemplateLibraryItem | undefined
  /** Items whose name/description/tags match a case-insensitive query (empty query → all). */
  search(query: string): TemplateLibraryItem[]
  /** Items in a category KEY (exact match). */
  filterByCategory(category: string): TemplateLibraryItem[]
  /** Items from a source KEY (exact match). */
  filterBySource(source: string): TemplateLibraryItem[]
}
