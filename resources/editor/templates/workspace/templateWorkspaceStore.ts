// Phase 26.34 — Template Library Admin Workspace store (editor-local).
//
// The admin browse surface over the Phase 26.21 Template Library. It reads ONLY the
// `TemplateLibraryProvider` (`templateLibrary`) — never TemplateProvider, the pattern registry, or
// BuilderState — and carries metadata only (no layout trees). "Use template" does NOT apply,
// resolve, insert, or dispatch a command: it hands off through the Phase 26.32 usage workflow, which
// opens the existing Phase 26.23 apply confirmation. The workspace never touches BuilderState.

import { defineStore } from 'pinia'
import { templateLibrary, type TemplateLibraryItem } from '../library'
import { useTemplateUsageStore } from '../usage'
import type { TemplatePackageMetadata } from '../package'
import {
  filterWorkspace,
  workspaceCategoryCounts,
  workspaceSourceCounts,
} from './workspaceFilters'
import { exportWorkspaceTemplates, type WorkspaceExportResult } from './workspaceExport'
import { importWorkspacePackage, type WorkspaceImportOutcome } from './workspaceImport'

interface State {
  items: TemplateLibraryItem[]
  loaded: boolean
  loading: boolean
  error: string | null
  query: string
  activeCategory: string | null
  activeSource: string | null
  /** The last export artifact (for the download action) — transient. */
  exportResult: WorkspaceExportResult | null
  /** The last import outcome (drives the success/error dialog) — transient. */
  importOutcome: WorkspaceImportOutcome | null
  /** The item currently being previewed (drives the preview modal) — transient. */
  previewItem: TemplateLibraryItem | null
}

export const useTemplateWorkspaceStore = defineStore('pbTemplateWorkspace', {
  state: (): State => ({
    items: [],
    loaded: false,
    loading: false,
    error: null,
    query: '',
    activeCategory: null,
    activeSource: null,
    exportResult: null,
    importOutcome: null,
    previewItem: null,
  }),

  getters: {
    /** The library is empty (loaded, no items at all) — drives the empty state. */
    isEmpty: (s): boolean => s.loaded && !s.error && s.items.length === 0,

    /** Source → category → search applied to the snapshot (metadata only). */
    results(s): TemplateLibraryItem[] {
      return filterWorkspace(s.items, { source: s.activeSource, category: s.activeCategory, query: s.query })
    },

    /** Items exist, but the current search/filter matched none — drives the no-results state. */
    noResults(s): boolean {
      return s.loaded && !s.error && s.items.length > 0 && this.results.length === 0
    },

    /** Category KEYS present in the library (key + count) for the chips. */
    categories(s): Array<{ key: string; count: number }> {
      const counts = workspaceCategoryCounts(s.items)
      return Object.keys(counts).map((key) => ({ key, count: counts[key] }))
    },

    /** Source KEYS present in the library (key + count) for the chips. */
    sources(s): Array<{ key: string; count: number }> {
      const counts = workspaceSourceCounts(s.items)
      return Object.keys(counts).map((key) => ({ key, count: counts[key] }))
    },
  },

  actions: {
    /** Re-read the library into reactive state (the workspace's only data source). Fault-isolated. */
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

    /**
     * Use a template: hand its selection to the usage workflow (which opens the existing apply
     * confirmation). The workspace applies nothing and touches no BuilderState.
     */
    use(item: TemplateLibraryItem): void {
      useTemplateUsageStore().useSelection({ templateId: item.templateId, source: item.source, item })
    },

    /** Open the preview for an item (presentation only — no apply, command, or runtime). */
    preview(item: TemplateLibraryItem): void {
      this.previewItem = item
    },

    /** Close the preview modal. */
    closePreview(): void {
      this.previewItem = null
    },

    /**
     * Export the currently visible results as a serialized package (reuses Phase 26.26). Stores the
     * artifact for the UI to download; returns it too. Resolves definitions read-only — no runtime.
     */
    exportResults(metadata: TemplatePackageMetadata): WorkspaceExportResult {
      this.exportResult = exportWorkspaceTemplates(this.results, metadata)
      return this.exportResult
    },

    /**
     * Import a package from uploaded JSON (reuses the atomic Phase 26.26 import + Phase 26.27
     * compatibility gate). On success, refresh the library so imported templates appear. Never
     * throws — invalid/incompatible/unsafe/duplicate packages become an error outcome for the dialog.
     */
    importPackage(json: string): WorkspaceImportOutcome {
      const outcome = importWorkspacePackage(json)
      this.importOutcome = outcome
      if (outcome.ok) this.load()
      return outcome
    },

    /** Dismiss the transient export/import result (closes the dialog). */
    clearTransfer(): void {
      this.exportResult = null
      this.importOutcome = null
    },
  },
})
