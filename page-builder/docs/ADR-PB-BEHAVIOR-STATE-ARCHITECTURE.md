# ADR-PB-BEHAVIOR-STATE-ARCHITECTURE — Editor State Machine & Behavior

**Status:** Accepted (architecture lock; automated gates GREEN + live EN/VI operator acceptance recorded; frozen at PB-CE-FREEZE-2R)
**Date:** 2026-08-06
**Phase:** PB-CE-FREEZE-1 / Stage 7
**Depends on (frozen):** ADR-PB-DATA-ARCHITECTURE, ADR-PB-EFFECTIVE-LAYOUT-OWNER-RESOLUTION, PB-STATE-2P/2V/2X.

---

## 1. Status

Accepted on the Community freeze. Server-observable guards `PageBuilderBehaviorStateGuardTest`
(5/107) + the tracked client Vitest state-machine suites are green; live EN/VI operator
acceptance recorded. Frozen at PB-CE-FREEZE-2R.

## 2. Context

The editor's runtime behaviour — dirty, saving, publishing, publication state, history,
selection, async work, session lifecycle — must have one authority and one state machine so
publish integrity, status truth, and EN/VI isolation hold. The client store (Pinia) is the
behaviour authority; the server owns persistence + derived truth.

## 3. State inventory

| State | Authority | Persistent? | Derived? | Mutators | Consumers | Reset/dispose |
|---|---|---:|---:|---|---|---|
| `currentPayload` | client store | no (posts to draft) | no | commands, undo/redo, inspector | save/preview/getters | replaced on load |
| `dirty` | client store | no | no | markDirty, commands | status, save guard | cleared only when persisted payload unchanged |
| `saving` | client store | no | no | persistDraft | status | reset in `finally` |
| `publishing` | client store | no | no | publish | status | reset in `finally` |
| `publishFailed` | client store | no | no | publish catch / persistDraft | status | cleared on new attempt/save |
| `publicationState` | derived getter | no | **yes** | — | status bar | — |
| `is_published_current` | server (`EditorContext`) | no | **yes** (draft vs published) | — | client status | — |
| selection / hover | client store | no | no | select/hover | overlays | — |
| collapse / contextMenu | client store | no | no | UI actions | tree/menu | — |
| history (undo/redo) | client store (`undoStack`/`redoStack`) | no | no | pushHistory | undo/redo | client-only, DB-free |
| clipboard | client store | no | no | copy | paste | — |
| geometry / overlay | `GeometryManager`/`OverlayManager` | no | no | DOM measure | overlays | `dispose()` removes listeners |
| inspector draft | client store | no | no | field edits | flushInspector | flushed before save |
| drag session | drag managers | no | no | drag | drop | ends on drop |
| preview state | client store | no | no | preview | canvas | debounced (`previewTimer`) |
| locale | boot / URL | — | — | navigation | context | full editor reload on switch |
| Render Source | `RenderSourceWriter` (server) | **yes** (`editor_mode`) | no | set toggle | frontend | ADR-RENDER-SOURCE |

**All UI/runtime state is transient and non-persistent.** Only `draft_payload`,
`published_payload`, `editor_mode` persist (ADR-DATA).

## 4. State machine

```text
Booting → Ready/Clean
Ready ──edit──▶ Dirty
Dirty ──saveDraft──▶ Saving ──ok──▶ Saved/Unpublished
Saved ──publish (serializes saveDraft first)──▶ Publishing ──ok──▶ Published
```

Error states: `SaveFailed` (dirty + typed error preserved), `PublishFailed`
(`publishFailed` set → status "Publish failed", never "Draft saved"), `PreviewFailed`.

Derived status precedence (client getter): `publishing` → local `dirty` (unsaved) →
`publishFailed` → server `is_published_current`. Status truth is sourced from the server
signal, never a local success flag (PB-STATE-2V).

## 5. Async / race audit (Stage 8)

| Operation | Overlap? | Serialization | Stale-response guard | Failure | Retry |
|---|---|---|---|---|---|
| saveDraft | joined | `savePromise` awaited before starting | dirty clears only if `currentPayload === payload` (reference identity) | typed error, stays dirty | user re-saves |
| publish | guarded (`publishing`) | **serializes saveDraft first; aborts on failed save** | publishes exactly the persisted draft | `publishFailed` set | user re-publishes |
| full/component preview | debounced (`previewTimer`) | independent of save | renders posted payload | preview error | re-trigger |
| context load | on boot/navigation | — | replaces store on load | error surfaced | reload |
| locale switch | full editor navigation to locale `editor_url` | save-and-switch / discard / cancel | **no in-place swap → no stale prior-locale response** | — | — |

Proven contracts: save→publish serialized; in-flight save joined; publish waits for
inline/inspector flush; dirty clears only for the persisted payload; failed publish never
reports Published; locale switch cannot apply a stale prior-locale response (it reloads).

## 6. Autosave policy

**Autosave is ABSENT in Community.** `AutosaveState` records intent metadata only (no worker,
queue, debounce, or persistence — guarded); no autosave route or job exists. Saving is
explicit (Ctrl+S / Save). Advanced autosave scheduling is a `FUTURE_EXTENSION`.

## 7. History / undo-redo

Client-only payload snapshots (`undoStack`/`redoStack`, `HISTORY_LIMIT`, `pushHistory` clears
redo). Undo/redo swap `currentPayload` and never write the DB. No server history/revision
column or route (guarded). Revisions are a `FUTURE_EXTENSION`.

## 8. Forbidden patterns

UI state persisting; publish bypassing a successful save; a stale async response overwriting
newer state; undo/redo writing the DB; preview mutating store authority; a local flag
(not the server signal) claiming Published; a hidden autosave; session dispose leaking
listeners.

## 9. Guards

- **Server (`PageBuilderBehaviorStateGuardTest`, PHP):** publication state derived-not-column,
  publish is a whole-draft atomic copy, autosave absent (intent-only + no route), no server
  undo/redo/revision route/column, client guard suites present.
- **Client (tracked Vitest):** `publicationState.store`, `publishAtomicity.store`,
  `BuilderStatusBar`, `localeSwitch`, `ExtensionApiFreeze` — the state-machine authority.

## 10. Consequences

One behaviour authority; publish integrity and status truth are locked; autosave/revisions are
clean future seams. No production change required — the audit found the model already single.

## 11. Acceptance checklist

- [x] One authority per state; UI state never persists.
- [x] Publish cannot bypass a successful save; stale responses can't overwrite newer state.
- [x] History is client-only payload snapshots; undo/redo DB-free.
- [x] Preview never mutates store authority; session dispose clears listeners.
- [x] Autosave absent and documented.
- [x] Publication status is server-derived truth.
- [ ] Live acceptance (Community freeze).
