// Phase 26.29 — projects the starter pack registry into the discovery surface.
//
// Reads the Phase 26.28 `TemplateStarterPackRegistry` LIVE and projects each pack to a metadata
// item. It does not copy or store packs (the registry is the single source of truth) and it never
// touches templates. find/search/filter all derive from the current projection, so newly
// registered packs surface immediately.

import { templateStarterPackRegistry, type TemplateStarterPackRegistry } from '../starterPacks'
import type { PackLibraryProvider } from './PackLibraryProvider'
import { toPackLibraryItem, type TemplatePackLibraryItem } from './packLibraryItem'
import {
  findPackItem,
  filterPacksByCategory,
  filterPacksBySource,
  searchPacks,
} from './packDiscoveryFilters'

export class StarterPackLibraryProvider implements PackLibraryProvider {
  constructor(private readonly registry: TemplateStarterPackRegistry = templateStarterPackRegistry) {}

  all(): TemplatePackLibraryItem[] {
    return this.registry.all().map(toPackLibraryItem)
  }

  find(id: string): TemplatePackLibraryItem | undefined {
    return findPackItem(this.all(), id)
  }

  search(query: string): TemplatePackLibraryItem[] {
    return searchPacks(this.all(), query)
  }

  filterByCategory(category: string): TemplatePackLibraryItem[] {
    return filterPacksByCategory(this.all(), category)
  }

  filterBySource(source: string): TemplatePackLibraryItem[] {
    return filterPacksBySource(this.all(), source)
  }
}

/** The active pack discovery provider (projects the default starter pack registry). */
export const packLibrary = new StarterPackLibraryProvider()
