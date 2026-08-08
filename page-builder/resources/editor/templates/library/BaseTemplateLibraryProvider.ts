// Phase 26.21 — Shared discovery base.
//
// Every provider's find/search/filter behavior is identical and derives entirely from `all()`.
// Subclasses implement only `all()` (returning immutable snapshots); the discovery surface is
// inherited so it stays consistent across builtin/user/imported/composite sources.

import type { TemplateLibraryItem } from './TemplateLibraryItem'
import type { TemplateLibraryProvider } from './TemplateLibraryProvider'
import {
  filterLibraryByCategory,
  filterLibraryBySource,
  findLibraryItem,
  searchLibrary,
} from './templateLibraryFilters'

export abstract class BaseTemplateLibraryProvider implements TemplateLibraryProvider {
  /** Immutable metadata snapshots. Subclasses define the source of items. */
  abstract all(): TemplateLibraryItem[]

  find(id: string): TemplateLibraryItem | undefined {
    return findLibraryItem(this.all(), id)
  }

  search(query: string): TemplateLibraryItem[] {
    return searchLibrary(this.all(), query)
  }

  filterByCategory(category: string): TemplateLibraryItem[] {
    return filterLibraryByCategory(this.all(), category)
  }

  filterBySource(source: string): TemplateLibraryItem[] {
    return filterLibraryBySource(this.all(), source)
  }
}
