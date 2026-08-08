// The editor store (Phase 9 shell → Phase 10 canvas → Phase 11 interaction).
//
// Layout mutations go through the Phase 8 command API (POST state/command); the
// store never hand-edits the payload structure. Because that endpoint is
// stateless (a fresh BuilderState per request), the clipboard and undo/redo
// history that BuilderState would normally hold cannot survive between calls —
// so the editor keeps those two concerns client-side here, while every
// structural change is still performed by the backend command.

import { toRaw } from 'vue'
import { defineStore } from 'pinia'
import { ApiClient } from '../api/client'
import { overlayManager } from '../geometry'
import { dragManager, dropManager, outlineDragController, resolveOutlineDrop } from '../drag'
import type { DragProjection, DragSubject, DropResolution, GhostState, OutlineDropTarget, OutlineZone } from '../drag'
import {
  buildNodeTree,
  childrenCount,
  componentLocation,
  componentPath,
  findComponentById,
  findComponentNode,
  getComponentId,
  isContainerId,
  selectionAfterDelete,
  type PathItem,
} from '../utils/layout'
import { createComponentPayload } from '../utils/palette'
import { canContain } from '../utils/nestingPolicy'
import { describeDropIntent, dropRejection, type DropIntent } from '../utils/dropIntent'
import { validateLayout, type LayoutIssue } from '../utils/layoutValidation'
import { fallbackNodeLabel, NAV_LABEL_MAX, NODE_LABEL_KEY } from '../utils/nodeLabel'
import { useNavigatorLocksStore } from './navigatorLocks'
import {
  EMPTY_PAYLOAD,
  type BootData,
  type CanvasMode,
  type ClipboardEntry,
  type ComponentKind,
  type ComponentReconcile,
  type ComponentMeta,
  type ComponentNode,
  type DefinitionMeta,
  type EditorContextData,
  type EditorMetadata,
  type EditorNav,
  type LayoutContext,
  type LayoutPreset,
  type LayoutPayload,
  type LivePatch,
  type OwnerContext,
  type Permissions,
  type TranslationLocaleOption,
} from '../types'

const HISTORY_LIMIT = 50
const PREVIEW_DEBOUNCE_MS = 300

// PB-STATE-2V — the truthful publication states the status bars render. Distinct
// from the local save flags so a saved draft that is not yet published reads as
// 'unpublished' (public content stale), and a failed publish as 'publish_failed'.
export type PublicationState =
  | 'saving'
  | 'publishing'
  | 'unsaved'
  | 'publish_failed'
  | 'published'
  | 'unpublished'
  | 'none'

interface ContextMenuState {
  visible: boolean
  x: number
  y: number
  targetId: string | null
}

interface State {
  boot: BootData | null
  client: ApiClient | null
  loading: boolean
  saving: boolean
  publishing: boolean
  // PB-STATE-2V — set when a publish request was attempted but did not complete, so
  // the status can read "Publish failed" instead of falling back to "Draft saved".
  publishFailed: boolean
  error: string | null
  owner: OwnerContext | null
  layout: LayoutContext | null
  metadata: EditorMetadata | null
  permissions: Permissions | null
  currentPayload: LayoutPayload
  selectedComponentId: string | null
  hoveredComponentId: string | null
  collapsedIds: string[]
  clipboard: ClipboardEntry | null
  commandLoading: boolean
  commandError: string | null
  undoStack: LayoutPayload[]
  redoStack: LayoutPayload[]
  contextMenu: ContextMenuState
  dirty: boolean
  inspectorDirty: boolean
  canvasMode: CanvasMode
  previewHtml: string
  previewLoading: boolean
  previewError: string | null
  previewTimer: number | null
  // Phase 21 — Live Component Runtime. `reconcileComponentId` is a one-shot
  // directive set by a component-data commit (consumed by the frontend Live
  // Builder to reconcile just that component); `componentReconcile` is the
  // published server render the iframe replaces the component's marker with.
  reconcileComponentId: string | null
  componentReconcile: ComponentReconcile | null
  componentReconcileError: string | null
  componentReconcileSeq: number
  geometryReady: boolean
  geometryVersion: number
  viewportRect: { width: number; height: number } | null
  dragActive: boolean
  dragState: DragProjection | null
  dragTarget: string | null
  dragEdge: DropResolution['edge']
  dragGhost: GhostState | null
  outlineTarget: OutlineDropTarget | null
}

function pickPayload(layout: LayoutContext | null): LayoutPayload {
  const chosen = layout?.draft_payload ?? layout?.published_payload
  return chosen ? { schema: chosen.schema, sections: chosen.sections ?? [] } : { ...EMPTY_PAYLOAD }
}

// structuredClone throws DataCloneError on Vue/Pinia reactive proxies in real
// browsers — which silently broke every history-recording command (the throw is
// swallowed by executeCommand's catch, so the payload update is rolled back).
// toRaw unwraps the proxy; JSON guarantees a plain, JSON-safe deep copy.
function clonePayload(payload: LayoutPayload): LayoutPayload {
  return JSON.parse(JSON.stringify(toRaw(payload ?? ({} as LayoutPayload)))) as LayoutPayload
}

// Suppress text selection and show a grabbing cursor document-wide while a drag
// is in flight (both visual and outline). Guarded for non-DOM test envs.
function setBodyDragging(active: boolean): void {
  if (typeof document === 'undefined' || !document.body) return
  document.body.classList.toggle('pb-editor-dragging', active)
}

// The Property Inspector holds unblurred edits in local state; the store asks it
// to flush those before Save Draft / Publish so nothing typed-but-not-blurred is
// lost. Kept as a module-level hook (like the geometry/drag singletons) rather
// than reactive state — it is a single imperative callback, not view data.
// Phase 24.2 — the inspector's flush Applies the staged draft and reports success
// so Save Draft / Publish can abort on a failed Apply (never save a stale payload).
let inspectorFlush: (() => Promise<boolean>) | null = null

// Phase 26.3, Increment 4.2 — the inspector's command dirty-guard. A structural
// command (move/delete/duplicate/cut/paste) awaits this before mutating so any
// un-Applied inspector draft is resolved through the SAME Apply/Discard/Stay
// dialog first. Resolves true to proceed (nothing staged, Applied cleanly, or
// discarded) and false to abort (Stay Here / failed Apply). Null (no inspector
// mounted) => no draft can be lost, so guardInspector proceeds.
let inspectorGuard: (() => Promise<boolean>) | null = null

// Live-patch subscriber (Phase 17). The frontend editor registers a callback
// that translates a pre-commit inspector edit into an instant iframe DOM patch.
// Module-level (not reactive) for the same reason as inspectorFlush; null in the
// structure editor, so emitLivePatch is a no-op there.
let livePatchSubscriber: ((patch: LivePatch) => void) | null = null

// Inline-edit flush (Phase 18). The frontend editor registers a callback that
// commits any open inline edit before a save/publish. Null in the structure
// editor (no inline editing), so flushInline is a no-op there.
let inlineFlush: (() => Promise<void>) | null = null

// PB-STATE-2P — the single in-flight draft save. Publish and a rapid Ctrl+S JOIN
// this instead of racing it: a second save awaits the first, then persists the
// latest payload — so publish never ships a draft older than the editor's current
// payload. Module-level (like the flush singletons) rather than reactive state, as
// it is imperative plumbing, not view data.
let savePromise: Promise<boolean> | null = null

export const useEditorStore = defineStore('pbEditor', {
  state: (): State => ({
    boot: null,
    client: null,
    loading: false,
    saving: false,
    publishing: false,
    publishFailed: false,
    error: null,
    owner: null,
    layout: null,
    metadata: null,
    permissions: null,
    currentPayload: { ...EMPTY_PAYLOAD },
    selectedComponentId: null,
    hoveredComponentId: null,
    collapsedIds: [],
    clipboard: null,
    commandLoading: false,
    commandError: null,
    undoStack: [],
    redoStack: [],
    contextMenu: { visible: false, x: 0, y: 0, targetId: null },
    dirty: false,
    // Phase 24.1 — the Inspector has staged (un-Applied) edits. Separate from
    // `dirty` (the page/BuilderState) so the exit/mode-switch guard can warn even
    // when only the inspector draft is unsaved.
    inspectorDirty: false,
    canvasMode: 'tree',
    previewHtml: '',
    previewLoading: false,
    previewError: null,
    previewTimer: null,
    reconcileComponentId: null,
    componentReconcile: null,
    componentReconcileError: null,
    componentReconcileSeq: 0,
    geometryReady: false,
    geometryVersion: 0,
    viewportRect: null,
    dragActive: false,
    dragState: null,
    dragTarget: null,
    dragEdge: null,
    dragGhost: null,
    outlineTarget: null,
    // Phase 26.48 — Save as Design UI intent (a captured node + kind). UI state
    // only: it never touches the payload, selection, or undo history.
    captureTarget: null as { node: Record<string, unknown>; kind: string } | null,
  }),

  getters: {
    // Phase 22.2 — cross-mode navigation links from the boot payload.
    navLinks: (state): EditorNav | null => state.boot?.nav ?? null,

    // LT-M1B Increment 2 — the server-prepared available-locales model for the
    // in-editor language switcher. Empty when the translation feature is off or
    // there is only one locale, so the switcher simply hides.
    availableLocales: (state): TranslationLocaleOption[] =>
      state.boot?.context?.translation?.available_locales ?? [],
    sections: (state) => state.currentPayload.sections ?? [],
    sectionCount: (state) => state.currentPayload.sections?.length ?? 0,
    canPublish: (state) => state.permissions?.can_publish ?? false,
    // PB-STATE-2V — server publish-truth: does the published snapshot reflect the
    // current draft? Sourced from the save/publish/context response, never a local flag.
    isPublishedCurrent: (state) => state.layout?.is_published_current ?? false,
    // PB-STATE-2V — the single truthful publication state both status bars render.
    // Precedence: in-flight work → local unsaved edits → a failed publish → server
    // publish-currency. A saved-but-unpublished draft resolves to 'unpublished'
    // (public content is stale), never a reassuring 'draft_saved'.
    publicationState: (state): PublicationState => {
      if (state.saving) return 'saving'
      if (state.publishing) return 'publishing'
      if (state.dirty) return 'unsaved'
      if (state.publishFailed) return 'publish_failed'
      const layout = state.layout
      if (!layout || (!layout.has_draft && !layout.has_published)) return 'none'
      if (layout.is_published_current && layout.has_published) return 'published'
      return 'unpublished'
    },
    isBuilderMode: (state) => state.layout?.editor_mode === 'builder',
    nodeTree(state): ComponentNode[] {
      return buildNodeTree(state.currentPayload)
    },
    selectedComponent(state): ComponentMeta | null {
      return findComponentById(state.currentPayload, state.selectedComponentId)
    },

    // Breadcrumb trail for the current selection, root section → selection.
    selectedPath(state): PathItem[] {
      return componentPath(state.currentPayload, state.selectedComponentId)
    },

    // Definition metadata (label/icon/…) for a node, by kind + type. Reactive on
    // metadata; used by the navigator and breadcrumb for labels and glyphs.
    definitionOf: (state) => (kind: string, type: string): DefinitionMeta | null => {
      const list = kind === 'section' ? state.metadata?.sections : state.metadata?.blocks
      return list?.find((d) => d.type === type) ?? null
    },

    // ---- Property Inspector (Phase 15) --------------------------------
    // The definition (label/icon/schema) of the selected component, or null.
    selectedDefinition(state): DefinitionMeta | null {
      const sel = findComponentById(state.currentPayload, state.selectedComponentId)
      if (!sel) return null
      const list = sel.kind === 'section' ? state.metadata?.sections : state.metadata?.blocks
      return list?.find((d) => d.type === sel.type) ?? null
    },

    // The selected component's data map (a plain clone — never the reactive
    // node), used to seed the inspector's local edit state.
    selectedData(state): Record<string, unknown> {
      const hit = findComponentNode(state.currentPayload, state.selectedComponentId)
      const data = hit?.node?.data
      return data && typeof data === 'object' && !Array.isArray(data) ? (data as Record<string, unknown>) : {}
    },

    // Current editing locale for translatable fields (boot-provided, default vi).
    currentLocale: (state): string => state.boot?.locale || 'vi',
    canUndo: (state) => state.undoStack.length > 0,
    canRedo: (state) => state.redoStack.length > 0,
    hasClipboard: (state) => state.clipboard !== null,
    isCollapsed: (state) => (id: string): boolean => state.collapsedIds.includes(id),
    isVisualMode: (state) => state.canvasMode === 'visual',

    // Geometry-backed rects for the overlays. Reading geometryVersion makes
    // these recompute on every refresh; the rect itself comes from the
    // GeometryManager, never from a DOM query in the overlay component.
    selectionRect: (state) =>
      state.geometryVersion >= 0 ? overlayManager.rect(state.selectedComponentId) : null,
    hoverRect: (state) =>
      state.geometryVersion >= 0 && state.hoveredComponentId !== state.selectedComponentId
        ? overlayManager.rect(state.hoveredComponentId)
        : null,

    // Human label for a component id ("Hero", "Section", "Block"…), derived
    // from its registered type/kind. Depends on geometryVersion so it tracks
    // marker (re)registration.
    labelFor: (state) => (id: string | null): string => {
      if (!id || state.geometryVersion < 0) return ''
      const entry = overlayManager.geometry.entry(id)
      if (!entry) return ''
      const raw = entry.type || entry.kind || ''
      return raw ? raw.charAt(0).toUpperCase() + raw.slice(1) : ''
    },

    // ---- Drag preview (Phase 14) --------------------------------------
    // The current drop resolution, or null when not dragging. The marker and
    // ghost read these; they never touch the drag session directly.
    dragResolution: (state): DropResolution | null => state.dragState?.resolution ?? null,
    dragValid: (state): boolean => state.dragState?.resolution?.valid ?? false,
    dragMarkerRect: (state) => state.dragState?.resolution?.markerRect ?? null,
    isDragging: (state): boolean => state.dragActive,

    // ---- Outline drag preview (Phase 14.4) ----------------------------
    // The key of the drop zone the pointer is over (valid or not) so the
    // matching zone can highlight itself, and whether that drop is legal.
    outlineActiveKey: (state): string | null => state.outlineTarget?.key ?? null,
    outlineTargetValid: (state): boolean => state.outlineTarget?.valid ?? false,
  },

  actions: {
    // ---- Lifecycle (Phase 9) ------------------------------------------
    bootstrap(boot: BootData) {
      this.boot = boot
      this.client = new ApiClient(boot)
      if (boot.context) {
        this.applyContext(boot.context)
      } else {
        void this.loadContext()
      }
    },

    applyContext(context: EditorContextData) {
      this.owner = context.owner
      this.layout = context.layout
      this.metadata = context.metadata
      this.permissions = context.permissions
      this.currentPayload = pickPayload(context.layout)
      this.dirty = false
      this.publishFailed = false
      this.undoStack = []
      this.redoStack = []
      this.closeContextMenu()
      // Phase 22.2 — restore a selection handed off from the other mode.
      this.applyBootSelection()
    },

    // Select the component named in the boot payload (?selected=…), if it still
    // exists. Invalid/stale ids are ignored so a handoff can never break the load.
    applyBootSelection() {
      const selected = this.boot?.selected
      if (selected && findComponentById(this.currentPayload, selected)) {
        this.selectComponent(selected)
      }
    },

    applyLayout(layout: LayoutContext) {
      this.layout = layout
    },

    async loadContext() {
      if (!this.client) return
      this.loading = true
      this.error = null
      try {
        this.applyContext(await this.client.getContext())
      } catch (e) {
        this.error = (e as Error).message
      } finally {
        this.loading = false
      }
    },

    // ---- Inspector flush bridge (Phase 15.2) --------------------------
    // The PropertyInspector registers a flush hook on mount; Save Draft /
    // Publish await it so unblurred field edits are committed into the payload
    // before it is sent.
    registerInspectorFlush(fn: () => Promise<boolean>) {
      inspectorFlush = fn
    },
    unregisterInspectorFlush(fn: () => Promise<boolean>) {
      if (inspectorFlush === fn) inspectorFlush = null
    },
    // Returns whether the staged inspector draft Applied cleanly (true when there
    // is nothing to apply). Save Draft / Publish abort when this is false.
    async flushInspector(): Promise<boolean> {
      return inspectorFlush ? await inspectorFlush() : true
    },
    // Phase 24.1 — the inspector reports whether it holds staged (un-Applied) edits.
    setInspectorDirty(value: boolean) {
      this.inspectorDirty = value
    },

    // ---- Structural guard bridge (Phase 26.3, Increment 4.2) ----------
    // The PropertyInspector registers a command dirty-guard on mount that reuses
    // its Apply/Discard/Stay dialog. Every structural mutation below awaits
    // guardInspector() so a staged draft is never silently discarded.
    registerInspectorGuard(fn: () => Promise<boolean>) {
      inspectorGuard = fn
    },
    unregisterInspectorGuard(fn: () => Promise<boolean>) {
      if (inspectorGuard === fn) inspectorGuard = null
    },
    async guardInspector(): Promise<boolean> {
      return inspectorGuard ? await inspectorGuard() : true
    },

    // Single lock authority (Phase 26.3, Part 13 + Increment 4.2). Locks are an
    // owner-scoped, editor-only concept; enforcing them HERE — at the store
    // command boundary every surface (navigator menu, canvas toolbar, keyboard
    // shortcuts, drag) delegates to — is what makes the guard consistent, rather
    // than each surface re-checking independently.
    isNodeLocked(id: string | null | undefined): boolean {
      return id != null && useNavigatorLocksStore().isLocked(this.owner, id)
    },

    /** The component type for an id from the current payload, or null. */
    nodeTypeOf(id: string | null): string | null {
      return id ? findComponentById(this.currentPayload, id)?.type ?? null : null
    },

    /**
     * The single SYNCHRONOUS pre-commit safety check every drag surface (visual
     * canvas, outline tree, live iframe) runs before mutating (Phase 26.3,
     * Increment 7). It re-asserts the {@link DropIntent} rules — locked
     * target/source, move-into-self, and NestingPolicy — through the one pure
     * `dropRejection`. Returns true when the drop must be blocked (the geometry
     * resolver has already surfaced the reason as an invalid marker, so callers
     * abort silently). Kept synchronous so the clean path commits in the same tick;
     * the inspector dirty-guard is awaited separately by each caller, only when a
     * draft is actually staged.
     */
    dropBlocked(intent: DropIntent): boolean {
      return dropRejection(intent, {
        parentType: (id) => this.nodeTypeOf(id),
        isLocked: (id) => this.isNodeLocked(id),
        isSelfOrDescendant: (parentId, sourceId) =>
          componentPath(this.currentPayload, parentId).some((item) => item.id === sourceId),
      }) !== null
    },

    /** Structural integrity issues in the current layout (empty = sound). */
    layoutIssues(): LayoutIssue[] {
      return validateLayout(this.currentPayload, this.metadata)
    },

    // ---- Live patch bridge (Phase 17) ---------------------------------
    // The frontend editor registers a subscriber to mirror pre-commit inspector
    // edits into the iframe DOM instantly. No subscriber (structure editor) =>
    // no-op. Never writes the payload; the commit path is unchanged.
    registerLivePatch(fn: (patch: LivePatch) => void) {
      livePatchSubscriber = fn
    },
    unregisterLivePatch(fn: (patch: LivePatch) => void) {
      if (livePatchSubscriber === fn) livePatchSubscriber = null
    },
    emitLivePatch(patch: LivePatch) {
      if (livePatchSubscriber && patch.componentId) livePatchSubscriber(patch)
    },

    // ---- Inline-edit flush bridge (Phase 18) --------------------------
    registerInlineFlush(fn: () => Promise<void>) {
      inlineFlush = fn
    },
    unregisterInlineFlush(fn: () => Promise<void>) {
      if (inlineFlush === fn) inlineFlush = null
    },
    async flushInline() {
      if (inlineFlush) await inlineFlush()
    },

    /**
     * Persist the current payload as the draft. Commits any open inline edit and
     * Applies the staged inspector draft first — aborting on a failed Apply so a
     * stale payload is never saved (§16) — and refuses a structurally broken tree
     * (Increment 7). Joins an in-flight save so a rapid Ctrl+S / Publish never races
     * or drops the latest payload. Returns whether the draft was persisted.
     */
    async saveDraft(): Promise<boolean> {
      if (!this.client) return false
      await this.flushInline()
      if (!(await this.flushInspector())) return false
      const issues = this.layoutIssues()
      if (issues.length > 0) {
        this.error = `Cannot save — invalid layout: ${issues[0].message}`
        return false
      }
      // Join any in-flight save before starting our own, so the latest payload is
      // the one that ends up persisted rather than an early-return dropping it.
      if (savePromise) await savePromise
      savePromise = this.persistDraft()
      try {
        return await savePromise
      } finally {
        savePromise = null
      }
    },

    /**
     * The actual draft write (already flushed + validated by the caller). Only
     * clears `dirty` when no newer edit landed while the request was in flight — a
     * later mutation replaces `currentPayload` with a new object, so
     * reference-inequality keeps the editor dirty and the next save re-persists it.
     */
    async persistDraft(): Promise<boolean> {
      if (!this.client) return false
      const payload = this.currentPayload
      this.saving = true
      // A fresh save supersedes any earlier failed publish; clear the flag so the
      // status reflects the new draft (which will read 'unpublished' until published).
      this.publishFailed = false
      this.error = null
      try {
        this.applyLayout(await this.client.saveDraft(payload))
        if (this.currentPayload === payload) this.dirty = false
        return true
      } catch (e) {
        this.error = (e as Error).message
        return false
      } finally {
        this.saving = false
      }
    },

    /**
     * Publish the current payload. Flushes pending edits, refuses a broken tree,
     * then PERSISTS the current payload and publishes exactly that draft (PB-STATE-2P).
     * A failed save ABORTS the publish with the editor left dirty and the typed save
     * error visible — publish can never ship a stale `published_payload`, and the
     * publish path never masks a save error by clearing it underneath.
     */
    async publish(): Promise<boolean> {
      if (!this.client || this.publishing) return false
      // Clear any prior failure at the start of a genuine attempt; it is re-set only
      // if THIS publish request does not complete (PB-STATE-2V).
      this.publishFailed = false
      await this.flushInline()
      if (!(await this.flushInspector())) return false
      const issues = this.layoutIssues()
      if (issues.length > 0) {
        this.error = `Cannot publish — invalid layout: ${issues[0].message}`
        return false
      }
      // Serialize save → publish: never publish until the current payload IS the
      // persisted draft. A failed save aborts here (dirty + error preserved).
      if (!(await this.saveDraft())) return false
      this.publishing = true
      try {
        this.applyLayout(await this.client.publish())
        return true
      } catch (e) {
        // A publish attempt that reached this point (draft persisted) but failed must
        // NOT fall back to a reassuring "Draft saved": record the failure so the
        // status reads "Publish failed" and the user knows the public page is stale.
        this.publishFailed = true
        this.error = (e as Error).message
        return false
      } finally {
        this.publishing = false
      }
    },

    markDirty() {
      this.dirty = true
    },

    exit() {
      const url = this.boot?.exitUrl
      if (url) {
        window.location.assign(url)
      } else {
        window.history.back()
      }
    },

    // ---- Selection / hover / collapse (Phase 10/11) -------------------
    selectComponent(id: string | null) {
      this.selectedComponentId = id
    },

    hoverComponent(id: string | null) {
      this.hoveredComponentId = id
    },

    /**
     * Scroll the visual canvas so the component is in view (Phase 15). Uses the
     * geometry engine's registered marker element — a no-op in Outline mode or
     * when the component isn't currently rendered.
     */
    scrollToComponent(id: string) {
      const element = overlayManager.geometry.entry(id)?.element
      if (element && typeof element.scrollIntoView === 'function') {
        element.scrollIntoView({ block: 'center', inline: 'nearest', behavior: 'smooth' })
      }
    },

    /** Select a component and bring it into view (navigator double-click / locate). */
    focusComponent(id: string) {
      this.selectComponent(id)
      this.scrollToComponent(id)
    },

    toggleCollapse(id: string) {
      const i = this.collapsedIds.indexOf(id)
      if (i === -1) this.collapsedIds.push(id)
      else this.collapsedIds.splice(i, 1)
    },

    /**
     * Ensure a node is expanded (Phase 26.3, Increment 6). Removing it from the
     * collapsed set reveals freshly inserted children — used after Add Inside so a
     * new child is never hidden behind a collapsed parent. A no-op when already
     * expanded (nodes default to expanded; `collapsedIds` holds only collapsed ones).
     */
    expandComponent(id: string) {
      const i = this.collapsedIds.indexOf(id)
      if (i !== -1) this.collapsedIds.splice(i, 1)
    },

    openContextMenu(id: string, x: number, y: number) {
      this.selectComponent(id)
      this.contextMenu = { visible: true, x, y, targetId: id }
    },

    closeContextMenu() {
      this.contextMenu = { ...this.contextMenu, visible: false, targetId: null }
    },

    // ---- Command execution (Phase 8 API) ------------------------------
    pushHistory(payload: LayoutPayload) {
      this.undoStack.push(clonePayload(payload))
      if (this.undoStack.length > HISTORY_LIMIT) this.undoStack.shift()
      this.redoStack = []
    },

    /**
     * Run a backend command against the current payload. Records history and
     * marks dirty only when the payload actually changed. Returns whether it did.
     */
    async executeCommand(command: string, params: Record<string, unknown> = {}): Promise<boolean> {
      if (!this.client) return false
      const before = this.currentPayload
      this.commandLoading = true
      this.commandError = null
      try {
        const result = await this.client.runCommand(before, command, params)
        const changed = JSON.stringify(result.payload) !== JSON.stringify(before)
        if (changed) {
          this.pushHistory(before)
          this.currentPayload = result.payload
          this.selectedComponentId = result.selection?.selected ?? null
          this.dirty = true
          this.maybeSchedulePreview()
        }
        return changed
      } catch (e) {
        this.commandError = (e as Error).message
        return false
      } finally {
        this.commandLoading = false
      }
    },

    // ---- Property editing (Phase 15) ----------------------------------
    /**
     * Commit a component's full data map through the update-component-data
     * command. That command doesn't re-select, so we restore whatever was
     * selected (usually the same component; on a selection switch, the new one).
     */
    async updateComponentDataById(id: string, data: Record<string, unknown>): Promise<boolean> {
      const keepSelection = this.selectedComponentId
      // Phase 21 — mark this single-component commit for component-level
      // reconciliation (the frontend Live Builder consumes the directive; the
      // structure editor ignores it, so its full-preview path is unchanged).
      this.reconcileComponentId = id
      const changed = await this.executeCommand('update-component-data', { component_id: id, data })
      if (!changed) this.reconcileComponentId = null
      this.selectComponent(keepSelection)
      return changed
    },

    /** Commit data for the currently selected component. */
    async updateSelectedData(data: Record<string, unknown>): Promise<boolean> {
      const id = this.selectedComponentId
      if (!id) return false
      return this.updateComponentDataById(id, data)
    },

    // ---- Node operations ----------------------------------------------
    /**
     * Remove a component by id — the single delete implementation every surface
     * (navigator menu, canvas toolbars, keyboard, inspector) delegates to (§4).
     *
     * Delete is a structural change, so it (a) clears any pending single-component
     * reconcile directive left by a prior data edit, forcing the Live Builder to
     * do a full preview refresh rather than reconcile a now-gone node (§8), and
     * (b) applies a deterministic selection fallback computed BEFORE removal:
     * next sibling → previous sibling → parent → null (§7). When the deleted node
     * is not the current selection, the existing selection is preserved if it
     * survives the removal.
     */
    async deleteComponent(id: string) {
      if (this.isNodeLocked(id)) return
      if (this.inspectorDirty && !(await this.guardInspector())) return
      this.reconcileComponentId = null
      const keep = this.selectedComponentId
      const wasSelected = keep === id
      const fallback = selectionAfterDelete(this.currentPayload, id)
      const removed = await this.executeCommand('remove-component', { component_id: id })
      if (!removed) return
      if (!wasSelected && keep && findComponentById(this.currentPayload, keep)) {
        this.selectComponent(keep)
      } else {
        this.selectComponent(fallback)
      }
    },

    async duplicateComponent(id: string) {
      if (this.isNodeLocked(id)) return
      if (this.inspectorDirty && !(await this.guardInspector())) return
      await this.executeCommand('duplicate-component', { component_id: id })
    },

    /**
     * Rename a node (Increment 5). The custom label is stored under the inert
     * `_label` meta key through the EXISTING update-component-data command — one
     * command, one undo snapshot, no new storage/command/schema field, and the
     * renderer never reads it. Locked nodes cannot rename; the inspector dirty
     * guard is reused. Committing the type/fallback label clears the custom name
     * (empty ⇒ fallback), and committing the unchanged value is a no-op (no
     * command, no undo entry). Returns false only when a lock or the dirty guard
     * blocked the rename — the caller keeps editing in that case.
     */
    async renameComponent(id: string, label: string): Promise<boolean> {
      if (this.isNodeLocked(id)) return false
      const hit = findComponentNode(this.currentPayload, id)
      if (!hit) return false
      const fallback = fallbackNodeLabel(this.definitionOf(hit.kind, hit.node.type), hit.node.type)
      const currentRaw = (hit.node.data as Record<string, unknown> | undefined)?.[NODE_LABEL_KEY]
      const current = typeof currentRaw === 'string' ? currentRaw.trim() : ''
      let next = label.trim().slice(0, NAV_LABEL_MAX)
      if (next === fallback) next = '' // typing the type label back = no custom name
      if (next === current) return true // unchanged → no command, no undo entry
      if (this.inspectorDirty && !(await this.guardInspector())) return false
      return this.updateComponentDataById(id, { [NODE_LABEL_KEY]: next })
    },

    /**
     * Move a component under `targetParentId` at `index` — the single move
     * implementation the navigator menu, canvas toolbar and keyboard shortcuts
     * delegate to (drag keeps its own committed path but shares the lock guard).
     * Blocked when the node is locked; awaits the inspector dirty-guard first.
     */
    async moveComponent(id: string, targetParentId: string | null, index: number): Promise<boolean> {
      if (this.isNodeLocked(id)) return false
      if (this.inspectorDirty && !(await this.guardInspector())) return false
      return this.executeCommand('move-component', {
        component_id: id,
        target_parent_id: targetParentId,
        index,
      })
    },

    /**
     * Re-proportion an existing container's columns to `widths` (Phase 26.1).
     * This is a STRUCTURAL change (columns may be added/removed), so it clears any
     * pending single-component reconcile directive, forcing the Live Builder into a
     * full preview refresh (§8) rather than reconciling a node that may no longer
     * exist. The container stays selected if it survives the change.
     */
    async applyColumnLayout(containerId: string, widths: string[]) {
      this.reconcileComponentId = null
      const changed = await this.executeCommand('apply-column-layout', {
        container_id: containerId,
        widths,
      })
      if (changed && findComponentById(this.currentPayload, containerId)) {
        this.selectComponent(containerId)
      }
    },

    /** Reverse a container's column order (structural → full preview refresh). */
    async reverseColumns(containerId: string) {
      this.reconcileComponentId = null
      const changed = await this.executeCommand('reverse-columns', {
        container_id: containerId,
      })
      if (changed && findComponentById(this.currentPayload, containerId)) {
        this.selectComponent(containerId)
      }
    },

    // Copy/cut keep the node client-side (the stateless endpoint has no clipboard).
    copyComponent(id: string) {
      const hit = findComponentNode(this.currentPayload, id)
      if (hit) this.clipboard = { mode: 'copy', node: hit.node, kind: hit.kind }
    },

    async cutComponent(id: string) {
      if (this.isNodeLocked(id)) return
      if (this.inspectorDirty && !(await this.guardInspector())) return
      const hit = findComponentNode(this.currentPayload, id)
      if (!hit) return
      this.clipboard = { mode: 'cut', node: hit.node, kind: hit.kind }
      await this.executeCommand('remove-component', { component_id: id })
    },

    async pasteInto(targetId: string | null = this.selectedComponentId) {
      const clip = this.clipboard
      if (!clip) return
      // Pasting targets the node itself (and/or its container) — a locked target
      // blocks the mutation before we ask the dirty-guard.
      if (this.isNodeLocked(targetId)) return
      if (this.inspectorDirty && !(await this.guardInspector())) return

      let parentId: string | null = null

      if (clip.kind === 'block') {
        const target = targetId ? findComponentNode(this.currentPayload, targetId) : null
        if (target) {
          parentId = target.canContainChildren ? getComponentId(target.node) : target.parentId
        } else {
          const secs = this.currentPayload.sections ?? []
          parentId = secs.length ? getComponentId(secs[secs.length - 1]) : null
        }
        // Never paste into a locked container even when the target resolves to a parent.
        if (this.isNodeLocked(parentId)) return
        if (!parentId) {
          this.commandError = 'Add a section before pasting a block.'
          return
        }
      }

      await this.executeCommand('insert-component', {
        component: clip.node,
        target_parent_id: parentId,
        index: null,
      })
    },

    // Selection-relative convenience wrappers (used by shortcuts / toolbar).
    deleteSelected() {
      if (this.selectedComponentId) void this.deleteComponent(this.selectedComponentId)
    },
    duplicateSelected() {
      if (this.selectedComponentId) void this.duplicateComponent(this.selectedComponentId)
    },
    copySelected() {
      if (this.selectedComponentId) this.copyComponent(this.selectedComponentId)
    },
    cutSelected() {
      if (this.selectedComponentId) void this.cutComponent(this.selectedComponentId)
    },

    // ---- Undo / redo (client-side; stateless endpoint can't hold history) ----
    undo() {
      const previous = this.undoStack.pop()
      if (previous === undefined) return
      this.redoStack.push(clonePayload(this.currentPayload))
      this.currentPayload = previous
      this.dirty = true
      this.maybeSchedulePreview()
    },

    redo() {
      const next = this.redoStack.pop()
      if (next === undefined) return
      this.undoStack.push(clonePayload(this.currentPayload))
      this.currentPayload = next
      this.dirty = true
      this.maybeSchedulePreview()
    },

    // ---- Visual canvas preview (Phase 12) -----------------------------
    setCanvasMode(mode: CanvasMode) {
      this.canvasMode = mode
      this.closeContextMenu()
      if (mode === 'visual') this.schedulePreview(0)
    },

    maybeSchedulePreview() {
      if (this.canvasMode === 'visual') this.schedulePreview()
    },

    schedulePreview(delay: number = PREVIEW_DEBOUNCE_MS) {
      if (this.previewTimer !== null) window.clearTimeout(this.previewTimer)
      this.previewTimer = window.setTimeout(() => {
        this.previewTimer = null
        void this.refreshPreview()
      }, delay)
    },

    async refreshPreview() {
      if (!this.client || !this.owner) return
      this.previewLoading = true
      this.previewError = null
      try {
        this.previewHtml = await this.client.renderPreview(
          this.currentPayload,
          this.owner.type,
          this.owner.id,
        )
      } catch (e) {
        this.previewError = (e as Error).message
      } finally {
        this.previewLoading = false
      }
    },

    // ---- Live Component Runtime (Phase 21) ----------------------------
    // Take (and clear) the pending single-component reconcile directive. The
    // frontend Live Builder calls this on every committed payload change: a
    // non-null id means "reconcile just this component"; null means the change
    // was structural (add/delete/move/undo) and needs a full preview refresh.
    consumeReconcileDirective(): string | null {
      const id = this.reconcileComponentId
      this.reconcileComponentId = null
      return id
    },

    /**
     * Render one component (by id) from the current payload through the server
     * and publish it as `componentReconcile` for the iframe to swap in place.
     * Returns false (and records the error) when there is no client/owner or the
     * request fails — the caller then falls back to a full preview refresh.
     */
    async renderComponentPreview(componentId: string): Promise<boolean> {
      if (!this.client || !this.owner) return false
      this.componentReconcileError = null
      try {
        const res = await this.client.renderComponentPreview(
          this.currentPayload,
          componentId,
          this.owner.type,
          this.owner.id,
        )
        this.componentReconcileSeq += 1
        this.componentReconcile = {
          componentId: res.component_id,
          html: res.html,
          kind: res.kind,
          type: res.type,
          warnings: res.warnings ?? [],
          version: this.componentReconcileSeq,
        }
        return true
      } catch (e) {
        this.componentReconcileError = (e as Error).message
        return false
      }
    },

    // ---- Geometry engine (Phase 13) -----------------------------------
    // The visual canvas binds the overlay engine to its live DOM here. The
    // listener bridges non-reactive geometry into reactive store state: each
    // refresh bumps geometryVersion (which overlay getters depend on) and
    // records the viewport size.
    attachGeometry(viewport: HTMLElement, host: HTMLElement) {
      overlayManager.setListener((version) => {
        this.geometryVersion = version
        const vr = overlayManager.geometry.getViewportRect()
        this.viewportRect = { width: vr.width, height: vr.height }
        this.geometryReady = true
      })
      overlayManager.attach(viewport, host)
    },

    // Re-scan markers after a preview swap, then measure.
    syncGeometry() {
      overlayManager.sync()
    },

    // Force an immediate re-measure (scroll/resize also trigger this via rAF).
    refreshGeometry() {
      overlayManager.refreshNow()
    },

    detachGeometry() {
      overlayManager.detach()
      overlayManager.setListener(null)
      this.geometryReady = false
    },

    // ---- Drag session engine (Phase 14) -------------------------------
    // The DragManager owns the pointer lifecycle; these actions are its
    // callbacks. Nothing here mutates the layout during a gesture — the layout
    // changes exactly once, in finishDrag, through the Phase 8 command bus.
    attachDrag(viewport: HTMLElement) {
      // Teach the drop resolver which components are containers. The marker kind
      // is always 'section'/'block', so container blocks are only knowable from
      // the payload — without this, drops into container blocks never resolve.
      dropManager.setContainerCheck((id) => isContainerId(this.currentPayload, id))
      dragManager.bind({
        onStart: (subject, x, y) => this.startDrag(subject, x, y),
        onMove: (x, y, resolution) => this.updateDrag(x, y, resolution),
        onDrop: (resolution) => {
          void this.finishDrag(resolution)
        },
        onCancel: () => this.cancelDrag(),
        getViewport: () => viewport,
      })
    },

    detachDrag() {
      dragManager.unbind()
      this.resetDragState()
    },

    /** Human/icon metadata for a component type from the editor metadata. */
    definitionFor(kind: string, type: string): DefinitionMeta | null {
      return this.definitionOf(kind, type)
    },

    /** Start a drag from a toolbar handle: build the subject and hand off. */
    beginDrag(id: string, event: PointerEvent) {
      const entry = overlayManager.geometry.entry(id)
      const loc = componentLocation(this.currentPayload, id)
      if (!entry || !loc) return

      const def = this.definitionFor(loc.kind, entry.type)
      const subject: DragSubject = {
        id,
        kind: entry.kind || loc.kind,
        type: entry.type,
        label: (def?.label as string) || (def?.name as string) || this.labelFor(id) || 'Component',
        icon: (def?.icon as string) ?? null,
        parentId: loc.parentId,
        index: loc.index,
      }
      dragManager.start(subject, event)
    },

    // ---- Palette insert (Phase 14.2) ----------------------------------
    /** Add a section from the palette at the end of the root (command API). */
    async addSectionFromPalette(definition: DefinitionMeta) {
      this.commandError = null
      await this.executeCommand('insert-component', {
        component: createComponentPayload(definition, 'section'),
        target_parent_id: null,
        index: this.sectionCount,
      })
    },

    /**
     * Add a block from the palette relative to the current selection:
     * into a selected section/container, or as a sibling of a selected leaf
     * block. With no valid target, surface a friendly error.
     */
    async addBlockFromPalette(definition: DefinitionMeta) {
      const parentId = this.blockInsertParent()
      if (!parentId) {
        this.commandError = 'Select a section or container to add this block.'
        return
      }
      // Converge on the single guarded insertion entry point (Phase 26.3.10):
      // nesting + lock + dirty-guard pre-checks and one undo snapshot, identical
      // to the Navigator and the live-builder "Add Element" picker paths.
      await this.insertComponent({ parentId, componentType: definition.type })
    },

    /**
     * Insert a layout preset (Phase 25.3): a Section → Container → Column tree, via
     * the existing insert-pattern command so it is ONE undo entry (ids regenerated
     * server-side, new root selected).
     */
    async addPresetFromPalette(preset: LayoutPreset) {
      this.commandError = null
      await this.executeCommand('insert-pattern', { sections: preset.sections })
    },

    /**
     * Insert a Builder Resource — a pre-built design section/block from the registry
     * palette (Phase 26.47E) — as a native, undoable element. The palette carries
     * only light metadata, so fetch the id-free payload by rid and dispatch it
     * through the EXISTING commands: insert-pattern for the section tree, or
     * insert-component to drop a block into the current container. No second
     * insertion engine; ids are regenerated server-side (one undo entry).
     */
    async insertBuilderResource(item: { rid: string; kind: string }): Promise<boolean> {
      return this.insertBuilderResourceByRid(item.rid, item.kind)
    },

    async insertBuilderResourceByRid(rid: string, kind: string): Promise<boolean> {
      if (!this.client) return false
      this.commandError = null
      try {
        const payload = await this.client.getBuilderResourcePayload(rid)
        // A block drops into the current container when one is selected; otherwise
        // it inserts as its self-contained host section (always valid + undoable).
        if (kind === 'block' && payload.block) {
          const parentId = this.blockInsertParent()
          if (parentId) {
            return await this.executeCommand('insert-component', {
              component: payload.block,
              target_parent_id: parentId,
              index: null,
            })
          }
        }
        const sections = payload.sections ?? []
        if (!sections.length) {
          this.commandError = 'This design has no insertable content.'
          return false
        }
        return await this.executeCommand('insert-pattern', { sections })
      } catch (e) {
        this.commandError = (e as Error).message
        return false
      }
    },

    // ---- Save as Design (Phase 26.48) ---------------------------------
    /**
     * Open the Save as Design dialog for a section/block by id. UI intent only —
     * capturing is an external persistence action that never mutates the payload,
     * selection, or undo history (Rules 13/20). Ignores unsupported kinds.
     */
    openCapture(id: string) {
      const meta = findComponentById(this.currentPayload, id)
      const hit = findComponentNode(this.currentPayload, id)
      if (!meta || !hit?.node) return
      if (meta.kind !== 'section' && meta.kind !== 'block') return
      this.captureTarget = { node: hit.node as Record<string, unknown>, kind: meta.kind }
    },

    closeCapture() {
      this.captureTarget = null
    },

    /**
     * Insert a NEW component (by registered type) under `parentId` at `index` —
     * the single insertion entry point the Navigator's Add Inside / Before / After
     * use, and the clean seam Increment 7 (Navigator drag) will reuse. It resolves
     * the definition + kind from editor metadata, builds the same palette payload
     * the drag/palette paths use, and commits through the existing insert-component
     * command (one undo entry; the new node is selected server-side).
     *
     * Guards mirror every other mutation: a section only lives at the root and a
     * block only inside a parent (a mismatch is rejected before the server), the
     * central NestingPolicy must allow the pairing, the target parent must be
     * unlocked, and the inspector dirty-guard runs first. `index` omitted/null
     * appends. Returns the new component id, or null when nothing was inserted
     * (unknown/mismatched type, disallowed nesting, locked target, dirty-guard
     * abort, or a failed command). The server stays authoritative — this only
     * pre-validates so the UI never issues a doomed command.
     */
    async insertComponent(options: {
      parentId: string | null
      componentType: string
      index?: number | null
    }): Promise<string | null> {
      const { parentId, componentType } = options

      const sectionDef = this.metadata?.sections?.find((d) => d.type === componentType) ?? null
      const blockDef = this.metadata?.blocks?.find((d) => d.type === componentType) ?? null
      // Root accepts only sections; a parented insert is always a block.
      const kind: ComponentKind | null = parentId === null ? (sectionDef ? 'section' : null) : (blockDef ? 'block' : null)
      const definition = kind === 'section' ? sectionDef : kind === 'block' ? blockDef : null
      if (!definition || !kind) {
        this.commandError = 'That component can’t be placed there.'
        return null
      }

      // Nesting: the server is authoritative, but reject an illegal pairing here
      // too so the UI never fires a command the mutator will refuse.
      if (parentId !== null) {
        const parentType = findComponentById(this.currentPayload, parentId)?.type
        if (!parentType || !canContain(parentType, componentType)) {
          this.commandError = 'That component can’t be placed there.'
          return null
        }
      }

      // Locks (Part 13) + inspector dirty-guard, in the same order as every other
      // structural mutation.
      if (this.isNodeLocked(parentId)) return null
      if (this.inspectorDirty && !(await this.guardInspector())) return null

      this.commandError = null
      const index = options.index ?? childrenCount(this.currentPayload, parentId)
      const changed = await this.executeCommand('insert-component', {
        component: createComponentPayload(definition, kind),
        target_parent_id: parentId,
        index,
      })
      // insert-component selects the new node server-side; executeCommand mirrors
      // that into selectedComponentId, so it is the freshly inserted component's id.
      return changed ? this.selectedComponentId : null
    },

    /** Resolve where a palette block should be inserted, from the selection. */
    blockInsertParent(): string | null {
      const sel = this.selectedComponent
      if (!sel) return null
      if (sel.kind === 'section' || isContainerId(this.currentPayload, sel.id)) return sel.id
      return sel.parentId // leaf block → sibling under its parent
    },

    /** Start a drag from a palette item (a new, not-yet-inserted component). */
    beginPaletteDrag(definition: DefinitionMeta, kind: ComponentKind, event: PointerEvent) {
      const subject: DragSubject = {
        id: `palette:${definition.type}:${Date.now()}`,
        kind,
        type: definition.type,
        label: (definition.label as string) || (definition.name as string) || definition.type,
        icon: (definition.icon as string) ?? null,
        parentId: null,
        index: 0,
        source: 'palette',
        component: createComponentPayload(definition, kind) as unknown as Record<string, unknown>,
      }
      if (import.meta.env.DEV) {
        // eslint-disable-next-line no-console
        console.debug('[page-builder] palette drag start', { type: definition.type, kind, bound: dragManager.isBound() })
      }
      dragManager.start(subject, event)
    },

    // ---- Outline drag (Phase 14.4) ------------------------------------
    // Outline mode drags against explicit DOM drop zones, resolved by the
    // OutlineDragController. It projects into the same reactive drag state
    // (ghost, active flag) and commits through the same command API on drop.
    attachOutlineDrag() {
      outlineDragController.bind({
        onStart: (subject, x, y) => this.startDrag(subject, x, y),
        onMove: (x, y, zone) => this.updateOutlineDrag(x, y, zone),
        onDrop: (zone) => {
          void this.finishOutlineDrag(zone)
        },
        onCancel: () => this.cancelDrag(),
      })
    },

    detachOutlineDrag() {
      outlineDragController.unbind()
      this.resetDragState()
    },

    /** Start dragging an existing outline node from its handle. */
    beginOutlineDrag(node: ComponentNode, event: PointerEvent) {
      const loc = componentLocation(this.currentPayload, node.id)
      if (!loc) return
      const def = this.definitionFor(node.kind, node.type)
      const subject: DragSubject = {
        id: node.id,
        kind: node.kind,
        type: node.type,
        label: (def?.label as string) || (def?.name as string) || this.labelFor(node.id) || 'Component',
        icon: (def?.icon as string) ?? null,
        parentId: loc.parentId,
        index: loc.index,
      }
      outlineDragController.start(subject, event)
    },

    /** Start dragging a palette item in outline mode (a not-yet-inserted node). */
    beginOutlinePaletteDrag(definition: DefinitionMeta, kind: ComponentKind, event: PointerEvent) {
      const subject: DragSubject = {
        id: `palette:${definition.type}:${Date.now()}`,
        kind,
        type: definition.type,
        label: (definition.label as string) || (definition.name as string) || definition.type,
        icon: (definition.icon as string) ?? null,
        parentId: null,
        index: 0,
        source: 'palette',
        component: createComponentPayload(definition, kind) as unknown as Record<string, unknown>,
      }
      outlineDragController.start(subject, event)
    },

    /** Resolve the zone under the pointer into a target and track the ghost. */
    updateOutlineDrag(x: number, y: number, zone: OutlineZone | null) {
      const subject = this.dragState?.subject
      if (!subject) return
      this.outlineTarget = resolveOutlineDrop(subject, zone, {
        isSelfOrDescendant: (parentId, subjectId) =>
          componentPath(this.currentPayload, parentId).some((item) => item.id === subjectId),
      })
      if (this.dragGhost) this.dragGhost = { ...this.dragGhost, x, y }
    },

    /** Commit an outline drop: insert (palette) or move (existing) — once. */
    async finishOutlineDrag(zone: OutlineZone | null) {
      const subject = this.dragState?.subject
      const target = subject
        ? resolveOutlineDrop(subject, zone, {
            isSelfOrDescendant: (parentId, subjectId) =>
              componentPath(this.currentPayload, parentId).some((item) => item.id === subjectId),
          })
        : null
      this.resetDragState()
      if (!subject || !target) return

      if (!target.valid) {
        this.commandError = target.reason
        return
      }
      this.commandError = null

      // Single pre-commit gate (Increment 7): lock + nesting + move-into-self (sync),
      // then the inspector dirty-guard. The outline resolver lands into a parent
      // slot, so the intent's reference is that parent (action descriptive only).
      const intent = describeDropIntent(
        subject,
        { edge: 'inside', targetId: target.parentId, parentId: target.parentId },
        (id) => this.nodeTypeOf(id),
      )
      if (this.dropBlocked(intent)) return
      if (this.inspectorDirty && !(await this.guardInspector())) return

      if (subject.source === 'palette') {
        if (!subject.component) return
        await this.executeCommand('insert-component', {
          component: subject.component,
          target_parent_id: target.parentId,
          index: target.index,
        })
        return
      }

      // The move command removes the node before re-inserting, so a same-parent
      // move to a later slot is compensated by one.
      let index = target.index
      if (subject.parentId === target.parentId && subject.index < index) index -= 1

      const moved = await this.executeCommand('move-component', {
        component_id: subject.id,
        target_parent_id: target.parentId,
        index,
      })
      if (moved) this.selectComponent(subject.id)
    },

    startDrag(subject: DragSubject, x: number, y: number) {
      if (subject.source !== 'palette') this.selectComponent(subject.id)
      this.dragActive = true
      this.dragState = { subject, resolution: null }
      this.dragTarget = null
      this.dragEdge = null
      this.dragGhost = { label: subject.label, type: subject.type, icon: subject.icon, x, y }
      setBodyDragging(true)
    },

    updateDrag(x: number, y: number, resolution: DropResolution) {
      if (!this.dragState) return
      this.dragState = { subject: this.dragState.subject, resolution }
      this.dragTarget = resolution.targetId
      this.dragEdge = resolution.edge
      if (this.dragGhost) this.dragGhost = { ...this.dragGhost, x, y }
    },

    async finishDrag(resolution: DropResolution | null) {
      const state = this.dragState
      this.resetDragState()
      if (!state || !resolution || !resolution.valid) return

      const { subject } = state

      // Single pre-commit gate (Increment 7): lock + nesting + move-into-self via
      // the shared DropIntent (sync), then the inspector dirty-guard — same as every
      // other structural mutation. Abort silently when blocked (marker showed why).
      const intent = describeDropIntent(subject, resolution, (id) => this.nodeTypeOf(id))
      if (this.dropBlocked(intent)) return
      if (this.inspectorDirty && !(await this.guardInspector())) return

      // Palette drags insert a brand-new component; moves relocate an existing
      // one. They use different commands and different index math.
      if (subject.source === 'palette') {
        await this.insertFromPalette(subject, resolution)
        return
      }

      const index = this.dropIndexFor(subject, resolution)
      if (index === null) return

      const moved = await this.executeCommand('move-component', {
        component_id: subject.id,
        target_parent_id: resolution.parentId,
        index,
      })

      // Keep the moved component selected regardless of the command's own
      // selection result, so the user doesn't lose their place after a drop.
      if (moved) this.selectComponent(subject.id)
    },

    /** Insert a palette-sourced component at the resolved drop (Phase 14.2). */
    async insertFromPalette(subject: DragSubject, resolution: DropResolution) {
      if (!subject.component) return
      const index = this.insertIndexFor(resolution)
      if (import.meta.env.DEV) {
        // eslint-disable-next-line no-console
        console.debug('[page-builder] palette drop → insert-component', {
          type: subject.type,
          parent: resolution.parentId,
          index,
        })
      }
      await this.executeCommand('insert-component', {
        component: subject.component,
        target_parent_id: resolution.parentId,
        index,
      })
      // insert-component selects the new node server-side; executeCommand mirrors
      // that into selectedComponentId, so no extra selection call is needed.
    },

    /**
     * Index for an *insert* (nothing is removed first, so no reorder
     * compensation): append inside a container, else the reference slot.
     */
    insertIndexFor(resolution: DropResolution): number {
      if (resolution.edge === 'inside' || !resolution.referenceId) {
        return childrenCount(this.currentPayload, resolution.parentId)
      }
      const ref = componentLocation(this.currentPayload, resolution.referenceId)
      if (!ref) return childrenCount(this.currentPayload, resolution.parentId)
      return resolution.edge === 'after' ? ref.index + 1 : ref.index
    },

    cancelDrag() {
      this.resetDragState()
    },

    resetDragState() {
      this.dragActive = false
      this.dragState = null
      this.dragTarget = null
      this.dragEdge = null
      this.dragGhost = null
      this.outlineTarget = null
      setBodyDragging(false)
    },

    /**
     * Translate a resolved drop into the numeric index the move command needs.
     * The backend removes the node before re-inserting, so a same-parent reorder
     * to a later slot is compensated by one; an "inside" drop appends.
     */
    dropIndexFor(subject: DragSubject, resolution: DropResolution): number | null {
      if (resolution.edge === 'inside') {
        const count = childrenCount(this.currentPayload, resolution.parentId)
        return subject.parentId === resolution.parentId ? Math.max(0, count - 1) : count
      }

      const ref = componentLocation(this.currentPayload, resolution.referenceId)
      if (!ref) return null

      let index = resolution.edge === 'after' ? ref.index + 1 : ref.index
      if (subject.parentId === resolution.parentId && subject.index < index) index -= 1
      return index
    },
  },
})
