// PB-TEMPLATE-REGISTRY-2-B — the ONE shared Resource Browser state authority.
//
// It owns ONLY presentation state (active kind, query, source/category/availability
// filters, selection, open/close) over an injected metadata catalog, and derives the
// filtered result set, counts, and filter options. It does NOT fetch localization,
// compute availability, discover providers, hold layout payloads, or touch the
// draft/version token — those stay with their certified authorities. The Structure
// Builder and the Frontend/Live Builder share this one store; there is no second
// browser state authority.
import { defineStore } from 'pinia'
import type {
  ActionError,
  AvailabilityFilter,
  FilterOption,
  ResourceBrowserItem,
  ResourceBrowserStrings,
  ResourceCatalog,
  ResourceCatalogBundle,
  ResourceKind,
  ResourceMaterializer,
} from './types'

/** The minimal client seam the browser needs (a subset of the editor ApiClient). */
export interface ResourceCatalogClient {
  getResourceCatalog(): Promise<ResourceCatalogBundle>
}

interface State {
  open: boolean
  catalog: ResourceCatalog | null
  strings: ResourceBrowserStrings
  // Admin-UI-locale labels for the invariant category keys (PB-FREE-LIBRARY-DESIGN-1-F).
  categoryLabels: Record<string, string>
  loaded: boolean
  loading: boolean
  error: string | null
  activeKind: ResourceKind
  query: string
  source: string
  category: string
  availability: AvailabilityFilter
  selectedRid: string | null
  // PB-TEMPLATE-REGISTRY-2-C — preview/detail + safe materialization state. Preview is
  // a server-rendered iframe keyed by the selected rid (no client payload), so it is
  // inherently non-mutating and race-free. Actions delegate to the injected materializer.
  detailOpen: boolean
  actionPending: boolean
  pendingTemplate: string | null
  actionError: ActionError | null
  materializer: ResourceMaterializer | null
  // Runtime Preview route base + editing locale for the (server-rendered) preview iframe.
  previewBase: string
  previewLocale: string
}

const EMPTY: ResourceCatalog = { patterns: [], templates: [], blocks: [] }

/** Normalize a (possibly legacy) catalog payload, tolerating a missing blocks bucket. */
function normalizeCatalog(c: Partial<ResourceCatalog> | null | undefined): ResourceCatalog {
  return {
    patterns: c?.patterns ?? [],
    templates: c?.templates ?? [],
    blocks: c?.blocks ?? [],
  }
}

/** Distinct invariant values (sorted) with per-value counts for the active kind. */
function optionsFor(items: ResourceBrowserItem[], pick: (i: ResourceBrowserItem) => string): FilterOption[] {
  const counts = new Map<string, number>()
  for (const item of items) {
    const value = pick(item)
    if (value === '') continue
    counts.set(value, (counts.get(value) ?? 0) + 1)
  }
  return [...counts.entries()]
    .sort(([a], [b]) => a.localeCompare(b))
    .map(([value, count]) => ({ value, count }))
}

export const useResourceBrowserStore = defineStore('pbResourceBrowser', {
  state: (): State => ({
    open: false,
    catalog: null,
    strings: {},
    categoryLabels: {},
    loaded: false,
    loading: false,
    error: null,
    activeKind: 'pattern',
    query: '',
    source: 'all',
    category: 'all',
    availability: 'all',
    selectedRid: null,
    detailOpen: false,
    actionPending: false,
    pendingTemplate: null,
    actionError: null,
    materializer: null,
    previewBase: '',
    previewLocale: '',
  }),

  getters: {
    /** All items for the active kind (server order preserved — server owns ordering). */
    items(state): ResourceBrowserItem[] {
      const catalog = state.catalog ?? EMPTY
      if (state.activeKind === 'pattern') return catalog.patterns
      if (state.activeKind === 'block') return catalog.blocks ?? []
      return catalog.templates
    },

    /** Filtered + searched result set for the active kind, in catalog order. */
    results(): ResourceBrowserItem[] {
      const q = this.query.trim().toLowerCase()
      return this.items.filter((item) => {
        if (this.source !== 'all' && item.source !== this.source) return false
        if (this.category !== 'all' && item.category !== this.category) return false
        if (this.availability === 'available' && !item.available) return false
        if (this.availability === 'unavailable' && item.available) return false
        if (q === '') return true
        const haystack = [item.name, item.description ?? '', item.category, ...item.tags].join(' ').toLowerCase()
        return haystack.includes(q)
      })
    },

    count(): number {
      return this.results.length
    },

    total(): number {
      return this.items.length
    },

    hasActiveFilters(state): boolean {
      return state.query !== '' || state.source !== 'all' || state.category !== 'all' || state.availability !== 'all'
    },

    /** The active kind exists but has no resources at all (empty catalog for kind). */
    isEmptyKind(state): boolean {
      return state.loaded && this.items.length === 0
    },

    /** Resources exist for the kind, but the current search/filters match none. */
    isNoResults(state): boolean {
      return state.loaded && this.items.length > 0 && this.results.length === 0
    },

    sourceOptions(): FilterOption[] {
      return optionsFor(this.items, (i) => i.source)
    },

    categoryOptions(): FilterOption[] {
      return optionsFor(this.items, (i) => i.category)
    },

    selectedItem(state): ResourceBrowserItem | null {
      if (!state.selectedRid) return null
      return this.items.find((i) => i.rid === state.selectedRid) ?? null
    },

    /** The selected resource is a whole-page Template (replaces the draft on apply). */
    selectedIsTemplate(): boolean {
      return this.selectedItem?.kind === 'template'
    },

    /** Canonical: the selected resource can be inserted/applied (available + not locked). */
    canActOnSelected(): boolean {
      const it = this.selectedItem
      return !!it && it.available && !it.locked
    },

    /** Stage-2 Template replacement confirmation is open. */
    isTemplateConfirmOpen(state): boolean {
      return state.pendingTemplate !== null
    },

    /** A resolved (Admin UI locale) chrome string, or the given fallback. */
    label(state) {
      return (key: string, fallback = ''): string => state.strings[key] ?? fallback
    },
  },

  actions: {
    /** Hydrate the catalog directly (tests / pre-fetched boot). */
    setCatalog(catalog: ResourceCatalog, strings?: ResourceBrowserStrings): void {
      this.catalog = normalizeCatalog(catalog)
      if (strings) this.strings = strings
      this.loaded = true
      this.error = null
    },

    /** Fetch the metadata-only catalog through the editor client (lazy, on open). */
    async load(client: ResourceCatalogClient, force = false): Promise<void> {
      if ((this.loaded && !force) || this.loading) return
      this.loading = true
      this.error = null
      try {
        const bundle = await client.getResourceCatalog()
        this.catalog = normalizeCatalog(bundle)
        this.strings = bundle.strings ?? {}
        this.categoryLabels = bundle.categoryLabels ?? {}
        this.loaded = true
      } catch {
        // Never surface raw transport internals (paths/exceptions) to the UI.
        this.error = 'Could not load resources. Please try again.'
      } finally {
        this.loading = false
      }
    },

    openBrowser(kind: ResourceKind = 'pattern'): void {
      this.open = true
      if (kind !== this.activeKind) this.setKind(kind)
    },

    closeBrowser(): void {
      this.open = false
      // Close any open detail/confirmation and clear transient action state. Selection
      // is left as-is (the browser is closed); reopening starts fresh per kind.
      this.detailOpen = false
      this.pendingTemplate = null
      this.actionError = null
    },

    /** Switch kind — a clean switch resets query, filters, and selection (one rule). */
    setKind(kind: ResourceKind): void {
      if (kind === this.activeKind) return
      this.activeKind = kind
      this.resetFilters()
      this.selectedRid = null
      this.detailOpen = false
      this.pendingTemplate = null
      this.actionError = null
    },

    setQuery(query: string): void {
      this.query = query
    },
    setSource(source: string): void {
      this.source = source
    },
    setCategory(category: string): void {
      this.category = category
    },
    setAvailability(availability: AvailabilityFilter): void {
      this.availability = availability
    },
    /** Select a resource and open its (non-mutating) preview/detail pane. */
    select(rid: string | null): void {
      this.selectedRid = rid
      this.detailOpen = rid !== null
      this.pendingTemplate = null
      this.actionError = null
    },

    resetFilters(): void {
      this.query = ''
      this.source = 'all'
      this.category = 'all'
      this.availability = 'all'
    },

    // ---- PB-TEMPLATE-REGISTRY-2-C — safe materialization -------------------

    /** Bind the injected materialization seam (editor insert + template import bridge). */
    bindMaterializer(materializer: ResourceMaterializer): void {
      this.materializer = materializer
    },

    /** Configure the (server-rendered) preview iframe: Runtime Preview base + editing locale. */
    configurePreview(opts: { previewBase?: string; locale?: string }): void {
      if (typeof opts.previewBase === 'string') this.previewBase = opts.previewBase
      if (typeof opts.locale === 'string') this.previewLocale = opts.locale
    },

    /**
     * Build the non-mutating preview URL for a rid at a device width. The Runtime Preview
     * route resolves the resource server-side and renders it read-only through the
     * production pipeline (no client payload). Empty when preview is not configured.
     */
    previewUrl(rid: string, device: 'desktop' | 'tablet' | 'mobile' = 'desktop'): string {
      if (!this.previewBase || !rid) return ''
      const sep = this.previewBase.includes('?') ? '&' : '?'
      const locale = this.previewLocale ? `&locale=${encodeURIComponent(this.previewLocale)}` : ''
      return `${this.previewBase}${sep}source=rid&ref=${encodeURIComponent(rid)}&device=${device}${locale}`
    },

    /** Close the detail/preview pane; also closes any confirmation and clears errors. */
    closeDetail(): void {
      this.detailOpen = false
      this.selectedRid = null
      this.pendingTemplate = null
      this.actionError = null
    },

    clearActionError(): void {
      this.actionError = null
    },

    /**
     * Insert the selected Pattern into the draft through the existing insert-pattern
     * command (via the materializer). Refuses an unavailable/locked resource with zero
     * mutation; guards double-submit; on success closes the browser; on failure keeps
     * the browser, the selection, and the draft, and surfaces a typed error.
     */
    async insertSelected(): Promise<void> {
      if (this.actionPending) return
      const it = this.selectedItem
      // Patterns (sections) and portable blocks both insert directly; templates
      // apply through the explicit two-stage confirmation, never here.
      if (!it || (it.kind !== 'pattern' && it.kind !== 'block')) return
      if (!(it.available && !it.locked)) {
        this.actionError = { code: 'unavailable', message: this.label('unavailableReason', 'This resource is unavailable.') }
        return
      }
      if (!this.materializer) return

      this.actionPending = true
      this.actionError = null
      try {
        const outcome =
          it.kind === 'block'
            ? await this.materializer.insertBlock(it.rid)
            : await this.materializer.insertPattern(it.rid)
        if (outcome.status === 'inserted') {
          this.closeBrowser()
        } else if (outcome.status === 'unavailable') {
          this.actionError = { code: 'unavailable', message: outcome.message || this.label('unavailableReason', 'This resource is unavailable.') }
        } else {
          this.actionError = { code: 'failed', message: outcome.message || this.label('actionFailed', 'The action could not be completed. Please try again.') }
        }
      } finally {
        this.actionPending = false
      }
    },

    /** Stage 1→2: open the explicit Template replacement confirmation (no mutation request). */
    requestTemplate(): void {
      const it = this.selectedItem
      if (!it || it.kind !== 'template') return
      if (!(it.available && !it.locked)) {
        this.actionError = { code: 'unavailable', message: this.label('unavailableReason', 'This resource is unavailable.') }
        return
      }
      this.pendingTemplate = it.rid
      this.actionError = null
    },

    /** Cancel the Template confirmation — closes the confirmation layer, sends ZERO request. */
    cancelTemplate(): void {
      this.pendingTemplate = null
    },

    /**
     * Stage 2: apply the confirmed Template to the DRAFT through the existing Full Page
     * Import authority (via the materializer). Draft-only; guards double confirm; on
     * success closes the browser; a stale/plan conflict preserves local work and surfaces
     * reconciliation (never auto-reload/merge); any other failure is a safe error.
     */
    async confirmTemplate(): Promise<void> {
      if (this.actionPending) return
      const rid = this.pendingTemplate
      if (!rid || !this.materializer) return

      this.actionPending = true
      this.actionError = null
      try {
        const outcome = await this.materializer.applyTemplate(rid)
        this.pendingTemplate = null
        if (outcome.status === 'applied') {
          this.closeBrowser()
        } else if (outcome.status === 'conflict') {
          this.actionError = { code: 'stale', message: outcome.message || this.label('templateConflict', 'The draft changed since the preview. Please review again.') }
        } else if (outcome.status === 'blocked') {
          this.actionError = { code: 'failed', message: outcome.message || this.label('templateBlocked', 'This template cannot be applied here.') }
        } else {
          this.actionError = { code: 'failed', message: outcome.message || this.label('actionFailed', 'The action could not be completed. Please try again.') }
        }
      } finally {
        this.actionPending = false
      }
    },
  },
})
