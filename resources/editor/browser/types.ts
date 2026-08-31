// PB-TEMPLATE-REGISTRY-2-B — unified Resource Browser types.
//
// A browser catalog item is METADATA ONLY: it never carries a resource's layout
// payload (that stays lazy for R2-C preview/insert). Human labels (name/description)
// are already resolved for the Admin UI locale by the server (H3 authority); every
// other field is locale-invariant machine data. Availability is the canonical server
// verdict and is never recomputed on the client.

/** The browser kind — a section resource is a pattern, a page resource a template. */
export type ResourceKind = 'pattern' | 'template'

export interface ResourceBrowserItem {
  /** Invariant identity (source:kind:resourceId). */
  rid: string
  kind: ResourceKind
  /** Invariant provider source (e.g. builtin, theme). */
  source: string
  /** Invariant category grouping key. */
  category: string
  /** Invariant tag keys. */
  tags: string[]
  /** Admin-UI-locale resolved label. */
  name: string
  /** Admin-UI-locale resolved description (nullable). */
  description: string | null
  /** Safe preview metadata ({thumbnail} or {sampleContent}); never a payload. */
  preview: Record<string, unknown> | null
  /** Canonical: does the resource resolve at all? */
  available: boolean
  /** Canonical: available but gated behind a capability the actor lacks. */
  locked: boolean
  /** Capability code when locked (machine identifier, localized client-side). */
  requiredCapability: string | null
}

export interface ResourceCatalog {
  patterns: ResourceBrowserItem[]
  templates: ResourceBrowserItem[]
}

/** Server-resolved browser chrome strings (Admin UI locale), keyed by stable slug. */
export type ResourceBrowserStrings = Record<string, string>

/** The catalog endpoint payload: metadata catalog plus resolved chrome strings. */
export interface ResourceCatalogBundle extends ResourceCatalog {
  strings?: ResourceBrowserStrings
  /** Admin-UI-locale labels for category keys (PB-FREE-LIBRARY-DESIGN-1-F). */
  categoryLabels?: Record<string, string>
}

export type AvailabilityFilter = 'all' | 'available' | 'unavailable'

/** A filter option: an invariant machine value plus a count for the active kind. */
export interface FilterOption {
  value: string
  count: number
}

// ---- PB-TEMPLATE-REGISTRY-2-C — safe materialization contracts -------------

/**
 * A typed, safe action outcome code. `unavailable` never reached the server;
 * `stale` is an optimistic-concurrency conflict (draft/template changed) that
 * preserves local work and asks the user to re-review; `failed` is any other safe
 * failure. No raw exception/path/token ever appears in an ActionError.
 */
export type ActionErrorCode = 'unavailable' | 'stale' | 'failed'

export interface ActionError {
  code: ActionErrorCode
  /** A localized, allowlisted reason — never a raw server/exception string. */
  message: string
}

/** Inserting a Pattern into the draft through the existing insert-pattern command. */
export type PatternInsertOutcome =
  | { status: 'inserted' }
  | { status: 'unavailable'; message?: string }
  | { status: 'failed'; message?: string }

/** Applying a Template to the draft (server-side, draft-only, atomic). */
export type TemplateApplyOutcome =
  | { status: 'applied' }
  | { status: 'conflict'; message?: string } // stale draft / changed template — re-review
  | { status: 'blocked'; message?: string } // plan blockers (missing/unavailable/unknown)
  | { status: 'failed'; message?: string }

/**
 * The materialization seam the browser delegates to. It owns NO catalog/preview
 * state and NO new persistence: patterns route through the editor's insert-pattern
 * command (one undo, ids regenerated once); templates go plan → commit → authoritative
 * context reload. Injected so the store stays pure/testable (mirrors ResourceCatalogClient).
 */
export interface ResourceMaterializer {
  insertPattern(rid: string): Promise<PatternInsertOutcome>
  applyTemplate(rid: string): Promise<TemplateApplyOutcome>
}
