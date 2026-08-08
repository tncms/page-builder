// Phase 26.22 — Template Picker store (editor-local, discovery only).
//
// The first user-facing browse/search/filter/SELECT surface over the Phase 26.21 Template
// Library. It reads ONLY the `TemplateLibraryProvider` (`templateLibrary`) — never
// TemplateProvider, LocalTemplateProvider, the pattern registry, or BuilderState. It carries no
// layout tree (metadata only), and it does NOT apply, resolve, insert, dispatch a command, or
// create an undo entry. Selecting a template records its identity only — the handoff seam a
// future Apply UX (Phase 26.23) will consume.

import { defineStore } from 'pinia'
import { templateLibrary, type TemplateLibraryItem } from '../library'
import {
  filterLibrary,
  libraryCategoryCounts,
  librarySourceCounts,
} from './templatePickerFilters'

interface State {
  items: TemplateLibraryItem[]
  loaded: boolean
  loading: boolean
  error: string | null
  query: string
  activeCategory: string | null
  activeSource: string | null
  selectedId: string | null
}

/** The identity a selection hands off (Phase 26.23 consumes this — the picker never applies). */
export interface TemplateSelection {
  templateId: string
  source: string
  item: TemplateLibraryItem
}

export const useTemplatePickerStore = defineStore('pbTemplatePicker', {
  state: (): State => ({
    items: [],
    loaded: false,
    loading: false,
    error: null,
    query: '',
    activeCategory: null,
    activeSource: null,
    selectedId: null,
  }),

  getters: {
    /** The library is empty (loaded, no items at all) — drives the empty state. */
    isEmpty: (s): boolean => s.loaded && !s.error && s.items.length === 0,

    /** Source → category → search applied to the snapshot (metadata only). */
    results(s): TemplateLibraryItem[] {
      return filterLibrary(s.items, { source: s.activeSource, category: s.activeCategory, query: s.query })
    },

    /** Items exist, but the current search/filter matched none — drives the no-results state. */
    noResults(s): boolean {
      return s.loaded && !s.error && s.items.length > 0 && this.results.length === 0
    },

    /** Category KEYS present in the library (key + count) for the chips. */
    categories(s): Array<{ key: string; count: number }> {
      const counts = libraryCategoryCounts(s.items)
      return Object.keys(counts).map((key) => ({ key, count: counts[key] }))
    },

    /** Source KEYS present in the library (key + count) for the chips. */
    sources(s): Array<{ key: string; count: number }> {
      const counts = librarySourceCounts(s.items)
      return Object.keys(counts).map((key) => ({ key, count: counts[key] }))
    },

    /** The currently selected item's metadata (if any). */
    selectedItem(s): TemplateLibraryItem | undefined {
      return s.selectedId ? s.items.find((i) => i.id === s.selectedId) : undefined
    },

    /** The selection handoff for a future Apply UX — identity only, never a resolved tree. */
    selection(): TemplateSelection | null {
      const item = this.selectedItem
      return item ? { templateId: item.templateId, source: item.source, item } : null
    },
  },

  actions: {
    /** Re-read the library into reactive state (the picker's only data source). Fault-isolated. */
    load(): void {
      this.loading = true
      this.error = null
      try {
        this.items = templateLibrary.all()
        this.loaded = true
      } catch (e: unknown) {
        this.items = []
        this.error = e instanceof Error ? e.message : 'Failed to load templates.'
      } finally {
        this.loading = false
      }
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

    /** Record a selection (identity only — no apply/resolve/command). */
    select(id: string | null): void {
      this.selectedId = id
    },
    clearSelection(): void {
      this.selectedId = null
    },
  },
})
