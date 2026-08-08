// Phase 26.29 — pack discovery provider contract.
//
// A DISCOVERY surface for packs, parallel to the Phase 26.21 `TemplateLibraryProvider` for
// templates. Read-only: browse / search / filter pack metadata. It never installs a pack, applies
// templates, resolves definitions, or mutates the pack registry — those are not this phase.

import type { TemplatePackLibraryItem } from './packLibraryItem'

export interface PackLibraryProvider {
  /** Every discoverable pack, as immutable metadata snapshots. */
  all(): TemplatePackLibraryItem[]
  find(id: string): TemplatePackLibraryItem | undefined
  search(query: string): TemplatePackLibraryItem[]
  filterByCategory(category: string): TemplatePackLibraryItem[]
  filterBySource(source: string): TemplatePackLibraryItem[]
}
