// Shared editor types (Phase 9). The backend metadata/context is the source of
// truth — these interfaces describe the shapes the shell consumes, nothing more.

export interface OwnerContext {
  type: string
  id: number | string
  label: string
  title: string
  edit_url: string | null
  // PB-CORE37-R1 — the Core content revision this editor session is based on. Echoed on
  // publish / translation creation; a stale value is a 409 (never a silent overwrite).
  content_revision?: number | null
}

export interface BlockNode {
  type: string
  id?: string
  data: Record<string, unknown>
}

export interface SectionNode {
  type: string
  id?: string
  data: Record<string, unknown>
  blocks: BlockNode[]
}

export interface LayoutPayload {
  schema?: string
  sections: SectionNode[]
}

export interface LayoutContext {
  editor_mode: string
  draft_payload: LayoutPayload | null
  published_payload: LayoutPayload | null
  has_draft: boolean
  has_published: boolean
  // PB-STATE-2V — whether the published snapshot already reflects the current draft.
  // The editor status is derived from this server truth, not from the local save flag.
  is_published_current: boolean
  // INV-PUB-06 — the server-issued optimistic-concurrency token. The client echoes the
  // last token it received on the next save/publish; a stale token is rejected (409).
  // Optional so pre-token response fixtures/factories stay valid; the store treats an
  // absent token as "not adopted" and never advances it optimistically.
  version_token?: string | null
  // PB-CORE-AUTHORITY-REMEDIATION-R1 — the public-state token (mode + published payload)
  // echoed on a render-source switch; a stale value is a 409 with zero writes.
  publication_token?: string | null
  last_saved_at: string | null
  published_at: string | null
}

export interface DefinitionMeta {
  type: string
  label?: string
  name?: string
  icon?: string
  category?: string
  [key: string]: unknown
}

// Phase 25.3 — a layout preset: a ready-to-insert Section → Container → Column
// tree, inserted via the insert-pattern command (one undo entry).
export interface LayoutPreset {
  id: string
  label: string
  icon: string
  columns: number
  sections: Array<Record<string, unknown>>
}

// Phase 26.1.1 — a lightweight preview descriptor for a column-layout preset: a
// data shape the popup turns into proportion bars / inline SVG (never a binary
// image). `segments` are relative weights (usually the numeric column widths).
export interface LayoutPresetPreview {
  type: string
  segments: number[]
}

// Phase 26.1 — a column-layout preset: a set of percent-string column widths
// applied to an existing container via apply-column-layout, or used to seed a
// new container's columns on a Section (one undo entry either way).
//
// Phase 26.1.1 enriches it with optional presentation metadata (category, icon,
// description, keywords, featured/recommended flags, order, preview). All new
// fields are optional so a bare { id, label, widths } stays valid.
export interface ColumnLayoutPreset {
  id: string
  label: string
  widths: string[]
  category?: string
  icon?: string
  description?: string
  keywords?: string[]
  featured?: boolean
  recommended?: boolean
  order?: number
  preview?: LayoutPresetPreview
}

// Phase 26.47E — a Builder Resource (a pre-built design section/block) projected
// into palette metadata. Light metadata only; the id-free payload is fetched on
// insert. `rid` is the public resource handle used for that fetch.
export interface BuilderResourceItem {
  rid: string
  kind: 'section' | 'block'
  name: string
  category: string
  tags: string[]
  source: string
  group: string
  version: string
  compatibility?: Record<string, unknown> | null
  preview?: Record<string, unknown> | null
  available: boolean
  locked: boolean
  requiredCapability?: string | null
  // Optional (Phase 26.47F) — shown in the Inspector when present, hidden otherwise.
  // Not populated by the registry today (which stays unchanged); reserved for a
  // future additive registry field so the Inspector displays it automatically.
  description?: string | null
}

export interface BuilderPaletteCategory {
  key: string
  label: string
  items: BuilderResourceItem[]
}

export interface BuilderPaletteGroup {
  key: string
  label: string
  categories: BuilderPaletteCategory[]
}

export interface BuilderResourcePalette {
  section: BuilderPaletteGroup[]
  block: BuilderPaletteGroup[]
}

// Phase 26.47E — the id-free payload the editor fetches to insert a resource:
// the full section tree (insert-pattern) and, for a block resource, its bare block
// node (insert-component into the current container).
export interface BuilderResourcePayload {
  rid: string
  kind: 'section' | 'block'
  sections: Array<Record<string, unknown>>
  block: Record<string, unknown> | null
}

// Phase 26.48 — the Save as Design response envelope.
export interface DesignCaptureResponse {
  status: 'saved' | 'duplicate' | 'invalid'
  rid?: string | null
  fingerprint?: string | null
  existingRid?: string | null
  issues?: Array<{ code: string; message: string }>
}

export interface EditorMetadata {
  version: string
  sections: DefinitionMeta[]
  blocks: DefinitionMeta[]
  tokens: Record<string, Record<string, unknown>>
  breakpoints: Record<string, unknown>
  presets?: LayoutPreset[]
  columnLayouts?: ColumnLayoutPreset[]
  // Phase 26.1.1 — canonical layout categories (id + label) for the popup tabs.
  layoutCategories?: { id: string; label: string }[]
  // Phase 26.47E — grouped design-resource sections/blocks for the native palette.
  builderResources?: BuilderResourcePalette
}

export interface Permissions {
  can_edit: boolean
  can_publish: boolean
  // Advisory (the mode endpoint re-checks): publish rights when the layout is
  // published, edit rights otherwise.
  can_switch_mode?: boolean
}

/** The server result of a render-source switch (`POST …/mode`). */
export interface ModeSwitchResult {
  editor_mode: string
  publication_token: string
  content_revision?: number | null
  has_published: boolean
}

export type ComponentKind = 'section' | 'block'

// A flattened-to-tree view of the layout the canvas renders (Phase 10). Purely
// derived from the payload — the payload stays the single source of truth.
export interface ComponentNode {
  id: string
  type: string
  kind: ComponentKind
  parentId: string | null
  depth: number
  disabled: boolean
  data: Record<string, unknown>
  children: ComponentNode[]
}

export interface ComponentMeta {
  id: string
  type: string
  kind: ComponentKind
  parentId: string | null
  disabled: boolean
}

// LT-M1B Increment 2 — one enabled locale in the translation group, as prepared
// SERVER-SIDE by LocaleSwitchProjector. The client never derives owner/layout/
// translation identity: `editor_url` is the server-generated, locale-aware target.
// A locale with no Core content translation has no `editor_url`; when the user may
// create it, `create_url` is the server endpoint that creates the translation (and
// its layout) and answers with the editor URL to open.
export interface TranslationLocaleOption {
  locale: string
  label: string
  is_source: boolean
  is_current: boolean
  has_translation: boolean
  has_layout: boolean
  editor_url: string | null
  can_create: boolean
  create_url: string | null
  // PB-CORE-AUTHORITY-REMEDIATION-R1 — what a new translated layout would be created
  // from (null when none is needed) and whether the explicit blank decision applies.
  source_layout?: 'absent' | 'empty' | 'cloneable' | null
  requires_blank_decision?: boolean
}

// Response of the translation-create endpoint.
export interface CreatedTranslation {
  locale: string
  created: boolean
  content_translation_id: number | null
  editor_url: string | null
  // PB-CORE37-R1 — the committed Core revision after the request.
  content_revision?: number | null
  // PB-CORE-AUTHORITY-REMEDIATION-R1 — how the translated layout was provisioned.
  layout?: 'clone' | 'blank'
}

// LT-M1B Increment 2 — the read-only translation context for the current editor
// request. Diagnostics + the switcher model; never authoritative on the client.
export interface TranslationContext {
  requested_locale: string | null
  resolved_locale: string
  source_locale: string
  is_source: boolean
  canonical_owner_type: string
  canonical_owner_id: number | string
  layout_owner_type: string
  layout_owner_id: number | string
  content_translation_id: number | null
  layout_id: number | null
  cloned: boolean
  baseline_captured: boolean
  available_locales: TranslationLocaleOption[]
}

export interface EditorContextData {
  owner: OwnerContext
  layout: LayoutContext
  metadata: EditorMetadata
  permissions: Permissions
  // LT-M1B Increment 2 — present only on the locale-aware boot/context; absent
  // (undefined) on the legacy path, so single-language editing is unchanged.
  translation?: TranslationContext | null
}

// Phase 22.2 — cross-mode navigation links, shared by both editors.
export interface EditorNav {
  structure: string | null
  live: string | null
  public: string | null
  classic: string | null
}

export interface BootData {
  mount: string
  csrf: string
  locale?: string
  context: EditorContextData | null
  exitUrl: string | null
  // Phase 22.2 — mode-switch links + an optional selection to restore on load.
  nav?: EditorNav
  selected?: string | null
  // PB-TEMPLATE-REGISTRY-2-D — server-resolved (Admin UI locale) editor chrome strings by
  // stable slug (unknown-component placeholder/reason copy). EN/VI at parity; the editor Vue
  // has no client i18n, so localization is owned by the plugin JSON + server resolution.
  strings?: Record<string, string>
  // PB-FREE-LIBRARY-DESIGN-1-F — Admin-UI-locale labels for the client-registered built-in
  // Template-Picker templates, keyed by template id. Applied to the library items at boot so
  // the Template Picker follows the Admin UI locale (never the editing locale).
  templateLabels?: Record<string, { name?: string; description?: string }>
  api: {
    context: string
    draft: string
    publish: string
    command: string
    preview: string
    // Phase 26.47F — the Runtime Preview route base for the resource Inspector
    // (`?source=rid&ref=<rid>&device=<device>`). Reuses the existing preview engine.
    resourcePreview?: string
    // PB-TEMPLATE-REGISTRY-2-C — Full Page Template import (draft-only, atomic) for the
    // unified Resource Browser. `plan` is a read-only dry run; `commit` replaces the draft.
    // Both resolve the RID server-side (the client never sends a builder payload).
    templateImportPlan?: string
    templateImport?: string
    // PB-TREG-2 — mint-at-insert orchestration + opaque-group renew/rebind lifecycle.
    // Optional so legacy boot payloads remain compatible; the client fails closed to a
    // recoverable state when a required provenance endpoint is absent.
    insertRegisteredResource?: string
    attestationRenew?: string
    attestationRebind?: string
    // PB-CORE-AUTHORITY-REMEDIATION-R1 — render-source switch for the effective owner.
    mode?: string
  }
}

// PB-TEMPLATE-REGISTRY-2-C — the read-only dry-run of a Full Page Template import. It
// carries the optimistic-concurrency tokens (planHash of the template tree, revision of
// the current draft) the commit must echo back. Metadata only — never a builder payload.
export interface FullPageImportPlanData {
  ok: boolean
  planHash: string
  revision: string
  blockers: Array<{ code: string; message: string }>
}

// The atomic commit result (draft only). `issues` carries typed blocker codes such as
// `stale_revision` / `plan_mismatch` on an optimistic-concurrency conflict.
export interface FullPageImportResultData {
  ok: boolean
  revision?: string
  issues?: Array<{ code: string; message: string }>
}

export type CanvasMode = 'tree' | 'visual'

// The BuilderState::toArray() shape returned by the command endpoint (Phase 8).
export interface StateResult {
  payload: LayoutPayload
  selection: { selected: string | null; hovered: string | null; panel: string | null }
  clipboard: { mode: string | null; has: boolean; payload: Record<string, unknown> | null }
  history: { can_undo: boolean; can_redo: boolean; [key: string]: unknown }
  dirty: { dirty: boolean; [key: string]: unknown }
  autosave: { requested: boolean; [key: string]: unknown }
}

// A component copied/cut on the client. The stateless command endpoint can't
// keep a server-side clipboard between requests, so the editor holds it here.
export interface ClipboardEntry {
  mode: 'copy' | 'cut'
  node: SectionNode | BlockNode
  kind: ComponentKind
}

export const EMPTY_PAYLOAD: LayoutPayload = { schema: 'pagebuilder/01', sections: [] }

// A live (pre-commit) inspector edit, forwarded to an optional subscriber so the
// frontend editor can patch the iframe DOM instantly (Phase 17). The structure
// editor registers no subscriber, so this is a no-op there.
export interface LivePatch {
  componentId: string | null
  type: string
  field: string
  value: unknown
}

// Raw result of the Phase 21 single-component preview endpoint (server render of
// ONE component, with editor markers, from the current unsaved payload).
// PB-XREG-3 — inert manifest of interactive behaviors in a render and the server-derived assets the canvas
// loads before mounting them. Never derived from (or written to) the layout payload.
export interface InteractiveManifest {
  behaviors: string[]
  assets: Array<{ handle: string; kind: 'css' | 'module'; url: string }>
}

export interface ComponentPreviewResult {
  component_id: string
  html: string
  kind: string
  type: string
  warnings: string[]
  interactive?: InteractiveManifest
}

// A committed single-component reconcile the frontend Live Builder consumes: the
// store publishes it after a component-data edit so the iframe can replace just
// that component's marker instead of swapping the whole canvas. `version` bumps
// per publish so identical re-commits still notify watchers.
export interface ComponentReconcile {
  componentId: string
  html: string
  kind: string
  type: string
  warnings: string[]
  interactive: InteractiveManifest | null
  version: number
}
