// Phase 26.14 — Pattern management store (editor-local).
//
// A UI layer over the registry: snapshot → search/filter → rename/duplicate/delete. Every
// mutation goes through `patternActions` (registry only) and then refreshes BOTH this
// store's snapshot and the picker, so lists update immediately. Management is a metadata
// operation — it NEVER dispatches a command, mutates the component tree, or creates an
// undo entry. Deleting a pattern does not touch already-inserted content (inserted trees
// are independent).

import { defineStore } from 'pinia'
import { PATTERN_LIBRARY_CATEGORIES, type PatternDefinition } from '../library'
import { patternProvider } from '../provider'
import { usePatternPickerStore } from '../picker/patternPickerStore'
import { usePatternRuntimeStore } from '../runtime/PatternRuntimeStore'
import { renamePattern, duplicatePattern, deletePattern, searchPatterns } from './patternActions'

interface State {
  patterns: PatternDefinition[]
  loaded: boolean
  query: string
  activeCategory: string | null
  renamingId: string | null
  renameDraft: string
  renameError: string | null
  deletingId: string | null
  feedback: string | null
}

export const usePatternManagementStore = defineStore('pbPatternManagement', {
  state: (): State => ({
    patterns: [],
    loaded: false,
    query: '',
    activeCategory: null,
    renamingId: null,
    renameDraft: '',
    renameError: null,
    deletingId: null,
    feedback: null,
  }),

  getters: {
    /** No patterns registered at all — drives the empty state. */
    isEmpty: (s): boolean => s.loaded && s.patterns.length === 0,

    /** Search + category filter applied. */
    results(s): PatternDefinition[] {
      return searchPatterns(s.patterns, s.query, s.activeCategory)
    },

    /** Patterns exist, but the current search/filter matched none. */
    noResults(s): boolean {
      return s.loaded && s.patterns.length > 0 && this.results.length === 0
    },

    /** Foundation categories that have at least one pattern (id + count). */
    categories(s): Array<{ key: string; count: number }> {
      return PATTERN_LIBRARY_CATEGORIES.map((key) => ({
        key,
        count: s.patterns.filter((p) => p.category === key).length,
      })).filter((c) => c.count > 0)
    },

    renamingPattern(s): PatternDefinition | undefined {
      return s.renamingId ? s.patterns.find((p) => p.id === s.renamingId) : undefined
    },
    deletingPattern(s): PatternDefinition | undefined {
      return s.deletingId ? s.patterns.find((p) => p.id === s.deletingId) : undefined
    },
  },

  actions: {
    /** Re-read the provider into reactive state. */
    load(): void {
      this.patterns = patternProvider.all()
      this.loaded = true
    },

    /** Re-snapshot this store AND the picker after a mutation. */
    refresh(): void {
      this.load()
      usePatternPickerStore().load()
    },

    setQuery(query: string): void {
      this.query = query
    },
    setCategory(category: string | null): void {
      this.activeCategory = category
    },
    clearFeedback(): void {
      this.feedback = null
    },

    // ---- Rename -------------------------------------------------------------
    openRename(id: string): void {
      const pattern = patternProvider.get(id)
      if (!pattern) return
      this.renamingId = id
      this.renameDraft = pattern.name
      this.renameError = null
    },
    setRenameDraft(value: string): void {
      this.renameDraft = value
    },
    cancelRename(): void {
      this.renamingId = null
      this.renameError = null
    },
    confirmRename(): boolean {
      if (!this.renamingId) return false
      const result = renamePattern(this.renamingId, this.renameDraft)
      if (!result.ok) {
        this.renameError = result.error ?? 'Rename failed.'
        return false
      }
      this.feedback = `Pattern renamed to "${result.pattern!.name}".`
      this.renamingId = null
      this.renameError = null
      this.refresh()
      return true
    },

    // ---- Duplicate ----------------------------------------------------------
    duplicate(id: string): PatternDefinition | null {
      const copy = duplicatePattern(id)
      if (copy) {
        usePatternRuntimeStore().trackCreated(copy.id) // a copy is a newly-created pattern
        this.feedback = `Pattern "${copy.name}" created.`
        this.refresh()
      }
      return copy
    },

    // ---- Delete -------------------------------------------------------------
    openDelete(id: string): void {
      if (patternProvider.get(id) !== undefined) this.deletingId = id
    },
    cancelDelete(): void {
      this.deletingId = null
    },
    confirmDelete(): boolean {
      if (!this.deletingId) return false
      const name = patternProvider.get(this.deletingId)?.name ?? 'Pattern'
      const removed = deletePattern(this.deletingId)
      this.deletingId = null
      if (removed) {
        this.feedback = `Pattern "${name}" deleted.`
        this.refresh()
      }
      return removed
    },
  },
})
