// Phase 26.31 — adoption discovery store (editor-local, read-only).
//
// A reactive browse/search/filter surface over adopted templates — the integration point a
// Picker/discovery UI consumes to show installed badges, pack provenance, and availability. It
// reads ONLY the `AdoptionDiscoveryProvider`. It records no selection, installs nothing, applies
// nothing, resolves no template, and never runs a command — this phase stops at discovery.

import { defineStore } from 'pinia'
import { adoptionDiscovery } from './AdoptionDiscoveryProvider'
import type { AdoptionTemplateItem } from './adoptionTemplateItem'
import {
  adoptionPackCounts,
  adoptionSourceCounts,
  filterAdoption,
  filterAvailableAdoption,
} from './adoptionFilters'

interface State {
  items: AdoptionTemplateItem[]
  loaded: boolean
  query: string
  activeSource: string | null
  activePack: string | null
  availableOnly: boolean
}

export const useAdoptionDiscoveryStore = defineStore('pbAdoptionDiscovery', {
  state: (): State => ({
    items: [],
    loaded: false,
    query: '',
    activeSource: null,
    activePack: null,
    availableOnly: false,
  }),

  getters: {
    isEmpty: (s): boolean => s.loaded && s.items.length === 0,

    results(s): AdoptionTemplateItem[] {
      return filterAdoption(s.items, {
        pack: s.activePack,
        source: s.activeSource,
        availableOnly: s.availableOnly,
        query: s.query,
      })
    },

    noResults(s): boolean {
      return s.loaded && s.items.length > 0 && this.results.length === 0
    },

    installedCount: (s): number => s.items.length,

    availableCount: (s): number => filterAvailableAdoption(s.items).length,

    packs(s): Array<{ key: string; count: number }> {
      const counts = adoptionPackCounts(s.items)
      return Object.keys(counts).map((key) => ({ key, count: counts[key] }))
    },

    sources(s): Array<{ key: string; count: number }> {
      const counts = adoptionSourceCounts(s.items)
      return Object.keys(counts).map((key) => ({ key, count: counts[key] }))
    },
  },

  actions: {
    /** Re-read the adoption discovery provider into reactive state (the store's only data source). */
    load(): void {
      this.items = adoptionDiscovery.all()
      this.loaded = true
    },
    setQuery(query: string): void {
      this.query = query
    },
    setSource(source: string | null): void {
      this.activeSource = source
    },
    setPack(pack: string | null): void {
      this.activePack = pack
    },
    setAvailableOnly(availableOnly: boolean): void {
      this.availableOnly = availableOnly
    },
  },
})
