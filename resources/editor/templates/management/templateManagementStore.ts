// Phase 26.19 — template management store (editor-local).
//
// A UI layer over the TemplateProvider: snapshot → search / category filter →
// rename / duplicate / delete. Every mutation goes through `templateActions` (provider only)
// and then re-snapshots this store, so lists update immediately. Management is a metadata
// operation — it NEVER dispatches a command, mutates the component tree, or creates an undo
// entry. Deleting a template does not touch already-applied content (applied trees are
// independent). `category` is an invariant KEY, never a localized label.

import { defineStore } from 'pinia'
import { TEMPLATE_LIBRARY_CATEGORIES } from '../authoring'
import { localTemplateProvider } from '../provider'
import type { TemplateDefinition } from '../contracts'
import { renameTemplate, duplicateTemplate, deleteTemplate, searchTemplates } from './templateActions'

interface State {
  templates: TemplateDefinition[]
  loaded: boolean
  query: string
  activeCategory: string | null
  renamingId: string | null
  renameDraft: string
  renameError: string | null
  deletingId: string | null
  feedback: string | null
}

export const useTemplateManagementStore = defineStore('pbTemplateManagement', {
  state: (): State => ({
    templates: [],
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
    /** No templates registered at all — drives the empty state. */
    isEmpty: (s): boolean => s.loaded && s.templates.length === 0,

    /** Search + category filter applied. */
    results(s): TemplateDefinition[] {
      return searchTemplates(s.templates, s.query, s.activeCategory)
    },

    /** Templates exist, but the current search/filter matched none. */
    noResults(s): boolean {
      return s.loaded && s.templates.length > 0 && this.results.length === 0
    },

    /** Category KEYS that have at least one template (key + count). */
    categories(s): Array<{ key: string; count: number }> {
      return TEMPLATE_LIBRARY_CATEGORIES.map((key) => ({
        key,
        count: s.templates.filter((t) => t.category === key).length,
      })).filter((c) => c.count > 0)
    },

    renamingTemplate(s): TemplateDefinition | undefined {
      return s.renamingId ? s.templates.find((t) => t.id === s.renamingId) : undefined
    },
    deletingTemplate(s): TemplateDefinition | undefined {
      return s.deletingId ? s.templates.find((t) => t.id === s.deletingId) : undefined
    },
  },

  actions: {
    /** Re-read the provider into reactive state. */
    load(): void {
      this.templates = localTemplateProvider.all()
      this.loaded = true
    },

    /** Re-snapshot after a mutation (the provider is the single source of truth). */
    refresh(): void {
      this.load()
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

    // ---- Rename ---------------------------------------------------------------
    openRename(id: string): void {
      const template = localTemplateProvider.get(id)
      if (!template) return
      this.renamingId = id
      this.renameDraft = template.name
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
      const result = renameTemplate(this.renamingId, this.renameDraft)
      if (!result.ok) {
        this.renameError = result.error ?? 'Rename failed.'
        return false
      }
      this.feedback = `Template renamed to "${result.template!.name}".`
      this.renamingId = null
      this.renameError = null
      this.refresh()
      return true
    },

    // ---- Duplicate ------------------------------------------------------------
    duplicate(id: string): TemplateDefinition | null {
      const copy = duplicateTemplate(id)
      if (copy) {
        this.feedback = `Template "${copy.name}" created.`
        this.refresh()
      }
      return copy
    },

    // ---- Delete ---------------------------------------------------------------
    openDelete(id: string): void {
      if (localTemplateProvider.get(id) !== undefined) this.deletingId = id
    },
    cancelDelete(): void {
      this.deletingId = null
    },
    confirmDelete(): boolean {
      if (!this.deletingId) return false
      const name = localTemplateProvider.get(this.deletingId)?.name ?? 'Template'
      const removed = deleteTemplate(this.deletingId)
      this.deletingId = null
      if (removed) {
        this.feedback = `Template "${name}" deleted.`
        this.refresh()
      }
      return removed
    },
  },
})
