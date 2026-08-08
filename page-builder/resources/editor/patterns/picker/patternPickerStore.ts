// Phase 26.12 — Pattern Picker store (editor-local).
//
// The UX layer over the Phase 26.11 foundation. It SNAPSHOTS the pattern registry into
// reactive state (Vue can't observe the plain registry singleton), drives category
// filtering + metadata preview, and inserts strictly through `insertPattern` — never
// `executeCommand`, never a direct BuilderState/selection mutation. Insertion inherits
// the foundation's guarantees (one command → one undo, server re-key, new root selected).

import { defineStore } from 'pinia'
import { useEditorStore } from '../../stores/editor'
import { insertPattern, PATTERN_LIBRARY_CATEGORIES, type PatternDefinition } from '../library'
import { patternProvider } from '../provider'
import { usePatternRuntimeStore } from '../runtime/PatternRuntimeStore'
import { filterByCategory } from './patternFilters'

interface State {
  open: boolean
  patterns: PatternDefinition[]
  loaded: boolean
  activeCategory: string | null
  selectedId: string | null
  inserting: string | null
}

export const usePatternPickerStore = defineStore('pbPatternPicker', {
  state: (): State => ({
    open: false,
    patterns: [],
    loaded: false,
    activeCategory: null,
    selectedId: null,
    inserting: null,
  }),

  getters: {
    /** No patterns registered yet — drives the empty state. */
    isEmpty: (s): boolean => s.loaded && s.patterns.length === 0,

    /** Patterns after the active category filter. */
    results(s): PatternDefinition[] {
      return filterByCategory(s.patterns, s.activeCategory)
    },

    /** The foundation categories that actually have patterns (id + count) for the chips. */
    categories(s): Array<{ key: string; count: number }> {
      return PATTERN_LIBRARY_CATEGORIES.map((key) => ({
        key,
        count: s.patterns.filter((p) => p.category === key).length,
      })).filter((c) => c.count > 0)
    },

    /** The pattern whose metadata preview is open (if any). */
    selectedPattern(s): PatternDefinition | undefined {
      return s.selectedId ? s.patterns.find((p) => p.id === s.selectedId) : undefined
    },
  },

  actions: {
    /** Re-read the provider into reactive state (the picker's only data source). */
    load(): void {
      this.patterns = patternProvider.all()
      this.loaded = true
    },

    openPicker(): void {
      this.load()
      this.open = true
    },
    closePicker(): void {
      this.open = false
      this.selectedId = null
    },
    toggle(): void {
      this.open ? this.closePicker() : this.openPicker()
    },

    setCategory(category: string | null): void {
      this.activeCategory = category
    },

    /** Open the metadata preview for a pattern (never renders the tree). */
    select(id: string | null): void {
      this.selectedId = id
    },

    /**
     * Insert a pattern by id through the foundation. A missing id (invalid selection)
     * does nothing. One call = one `insert-pattern` command = one undo entry.
     */
    async insert(id: string): Promise<boolean> {
      if (this.inserting) return false
      const pattern = patternProvider.get(id) ?? this.patterns.find((p) => p.id === id)
      if (!pattern) return false
      this.inserting = id
      try {
        const ok = await insertPattern(pattern, useEditorStore())
        if (ok) usePatternRuntimeStore().trackUsed(id) // runtime-only "recently used"
        return ok
      } finally {
        this.inserting = null
      }
    },
  },
})
