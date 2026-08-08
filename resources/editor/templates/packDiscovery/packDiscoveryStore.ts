// Phase 26.29 — pack discovery store (editor-local, read-only).
//
// A reactive browse/search/filter surface over the pack discovery provider — the integration point
// a future Picker/discovery UI consumes. It reads ONLY the `PackLibraryProvider`. It records no
// selection, installs nothing, applies nothing, and resolves no template — this phase stops at
// discovery.

import { defineStore } from 'pinia'
import { packLibrary } from './StarterPackLibraryProvider'
import type { TemplatePackLibraryItem } from './packLibraryItem'
import { filterPacks, packCategoryCounts, packSourceCounts } from './packDiscoveryFilters'

interface State {
  items: TemplatePackLibraryItem[]
  loaded: boolean
  query: string
  activeCategory: string | null
  activeSource: string | null
}

export const usePackDiscoveryStore = defineStore('pbPackDiscovery', {
  state: (): State => ({
    items: [],
    loaded: false,
    query: '',
    activeCategory: null,
    activeSource: null,
  }),

  getters: {
    isEmpty: (s): boolean => s.loaded && s.items.length === 0,

    results(s): TemplatePackLibraryItem[] {
      return filterPacks(s.items, { source: s.activeSource, category: s.activeCategory, query: s.query })
    },

    noResults(s): boolean {
      return s.loaded && s.items.length > 0 && this.results.length === 0
    },

    categories(s): Array<{ key: string; count: number }> {
      const counts = packCategoryCounts(s.items)
      return Object.keys(counts).map((key) => ({ key, count: counts[key] }))
    },

    sources(s): Array<{ key: string; count: number }> {
      const counts = packSourceCounts(s.items)
      return Object.keys(counts).map((key) => ({ key, count: counts[key] }))
    },
  },

  actions: {
    /** Re-read the pack discovery provider into reactive state (the store's only data source). */
    load(): void {
      this.items = packLibrary.all()
      this.loaded = true
    },
    setQuery(query: string): void {
      this.query = query
    },
    setCategory(category: string | null): void {
      this.activeCategory = category
    },
    setSource(source: string | null): void {
      this.activeSource = source
    },
  },
})
