# ADR-PB-DATA-ARCHITECTURE — Page Builder Single Source of Truth

**Status:** Accepted (architecture lock; automated gates GREEN + live EN/VI operator acceptance recorded; frozen at PB-CE-FREEZE-2R)
**Date:** 2026-08-06
**Phase:** PB-ADR-DATA-1 (data-architecture audit + guard lock)
**Depends on (frozen):** Layout Storage (Phase 2), ADR-PB-RENDER-SOURCE-001, ADR-PB-TRANSLATABLE-FIELD-REPRESENTATION, ADR-PB-EFFECTIVE-LAYOUT-OWNER-RESOLUTION, PB-STATE-2P/2V/2X.
**Supersedes / amends:** nothing. Consolidates existing data contracts into one authority map.

---

## 1. Status

Accepted. Every automated gate (§14, §Acceptance) passes and the live EN/VI acceptance
(§Live acceptance / Work N) was recorded by the operator (VI publish/frontend verified,
EN isolated). Frozen at PB-CE-FREEZE-2R.

## 2. Context

Before adding Revision, Autosave, Global/Reusable Blocks, Theme/Landing/AI Builder, or
Ecommerce Landing surfaces, the Page Builder's data must have a single source of truth.
Owner resolution is already locked (ADR-PB-EFFECTIVE-LAYOUT-OWNER-RESOLUTION). This ADR
locks **what data exists, who owns it, how it flows, and what may never happen to it.**

## 3. Motivation

Past defects (2P/2V/2T/2X) all shared a root shape: an authority was duplicated or
bypassed (client shipped stale published; status read a local flag; values resolved under
the wrong locale; publish targeted the wrong owner). This ADR removes the *possibility* of
a hidden or duplicated data authority.

## 4. Terminology

- **Persistent** — stored in `pb_layouts`.
- **Derived / Projection** — computed from persistent data, never stored (e.g. publication state).
- **Transient** — editor/runtime working state, never stored (selection, hover, geometry, history, clipboard, dirty, autosave, markers).
- **Payload** — the canonical layout tree (`schema` → `sections` → `blocks` → `data`).

## 5. Data inventory

| Data | Storage | Authority | Mutable by | Producer | Consumer | Lifetime |
|---|---|---|---|---|---|---|
| `pb_layouts` row | DB | `LayoutStore` | LayoutStore | first write | everything | Persistent |
| `owner_type` / `owner_id` | DB column | `LayoutStore` (from effective owner) | LayoutStore | resolver | all reads | Persistent |
| `editor_mode` (= Render Source storage) | DB column | `RenderSourceWriter` / editor mode toggles → `LayoutStore` | LayoutStore | setEditorMode | Frontend, editor | Persistent |
| `draft_payload` | DB column | `LayoutStore::saveDraft` | LayoutStore | editor / clone / import | editor, preview, publish | Persistent |
| `published_payload` | DB column | `LayoutStore::publish` | LayoutStore | publish (copy of draft) | Frontend, Render Source | Persistent |
| `draft_updated_by` / `published_by` | DB column | `LayoutStore` | LayoutStore | save/publish | audit | Persistent |
| Render Source (`RenderSource` enum) | derived from `editor_mode` | `PageBuilderLayout::renderSource()` | — | model accessor | Frontend switch | Derived |
| publication state (`is_published_current`) | none | `EditorContext::publishedIsCurrent` (server) + client `publicationState` getter | — | draft-vs-published compare | status bar | Derived / Projection |
| `currentPayload` | client store | Vue editor store | commands | editor edits | save/preview | Transient (editor) |
| selection / hover | client store + `SelectionState` | editor | user | UI | overlays | Transient |
| geometry / overlay | client store | `GeometryManager` / `overlayManager` | DOM measure | layout | overlays | Transient (runtime) |
| history (undo/redo) | client store + `HistoryState` | editor | commands | edits | undo | Transient |
| clipboard | client store + `ClipboardState` | editor | copy | paste | Transient |
| dirty / autosave | `DirtyState` / `AutosaveState` | editor | edits | — | save flow | Transient |
| editor render markers | HTML output only | `LayoutRenderer::renderForEditor` | — | render | iframe DOM map | Transient (render-time) |
| component / section definitions | registry (config/boot) | `SectionRegistry` / `BlockRegistry` | boot | plugin | editor metadata, render | Runtime (definition) |
| editor metadata | derived from registries | `PageBuilderManager::editorMetadata()` | — | registries | editor boot | Projection |
| translation payload / envelopes | inside `data` of the owner's payload | payload (per owner) | editor writes | inspector | Localize | Persistent (as payload) |
| layout bindings / baselines (LT) | LT tables + target `draft_payload` | LT services → `LayoutStore` | clone/sync | LT | LT diff/sync | Persistent |

Dead: none identified.

## 6. Ownership (one authority each)

- `draft_payload` — **`LayoutStore::saveDraft`**. Producers: editor `saveDraft`, LT clone/baseline/sync, import, composition-migrate console. Consumers: editor, preview, publish. **Never** the public frontend.
- `published_payload` — **`LayoutStore::publish`** (copy of `draft_payload`). Consumers: public frontend, Render Source read. **Never** the editor or the stateless preview endpoints.
- `editor_mode` / Render Source — **`RenderSourceWriter`** (write, fail-closed) + editor mode toggles, all through **`LayoutStore::setEditorMode`**. Read via `PageBuilderLayout::renderSource()`.
- publication state — **derived only** (`draft_payload` vs `published_payload`); never persisted, never a client-authored flag.
- effective owner — **`EffectiveEditorOwnerResolver`** (ADR-PB-EFFECTIVE-LAYOUT-OWNER-RESOLUTION).
- translation value — **per-locale envelope**, resolved by **`Localize`** under one explicit locale (ADR-PB-TRANSLATABLE-FIELD-REPRESENTATION).

**Proven:** the three persisted columns are assigned in exactly one file (`LayoutStore`, 3 lines) — guarded. No field has two authorities.

> Naming caution: `LayoutStore::setEditorMode(string)` (persisted Render Source) and
> `ComponentRuntime::setEditorMode(bool)` (transient "render with markers" flag) share a
> method name but are different concepts in different domains. The runtime flag is never
> persisted.

## 7. Payload contract

Canonical shape (enforced by `LayoutNormalizer`, schema `pagebuilder/01`):

```text
{ schema, sections: [ { type, id, data, blocks: [ { type, id, data, blocks? } ] } ] }
```

- **Canonical:** `schema`, `sections`, `type`, `id` (stable, `ComponentId::assign`), `data`, `blocks`.
- **Derived:** none inside the payload — derived data lives outside it.
- **Forbidden:** any other top-level key, any other node-level key (the normalizer discards unknown keys), and any transient/runtime key inside `data`.

`data` is canonical component content/settings, including translation envelopes. It must
never carry transient UI state.

## 8. Payload lifecycle

```text
Inspector / commands
  → currentPayload            (client store, single client authority)
  → client.saveDraft({payload})
  → LayoutNormalizer::normalize + ComponentId::assign
  → LayoutStore::saveDraft → draft_payload
  → client.publish({})        (no payload sent — server-authoritative)
  → LayoutStore::publish → published_payload   (byte copy of draft_payload)
  → FrontendRenderer → published_payload → Blade → HTML
```

No alternative path, no side write, no hidden synchronization. Preview renders the posted
`currentPayload` (draft), never a stored row.

## 9. Render authority

| Surface | Reads | Never |
|---|---|---|
| Public frontend (`FrontendRenderer`) | `published_payload` | draft |
| Preview endpoint (`EditorPreviewController`) | posted payload (current/draft) | stored published |
| Component preview (`ComponentPreviewController`) | posted payload | stored published |
| Frontend editor iframe (`FrontendEditorRenderer`) | `draft_payload`, seeding from `published_payload` **only when no draft exists yet** (first-open visual context), else classic body | stale published once a draft exists |
| Render Source decision (`FrontendRenderer`) | `published_payload` + `editor_mode` | draft |

The editor iframe's published-seed-on-first-open is an intentional, named fallback (a blank
canvas otherwise); it is an **editor** surface, not the public frontend and not the
stateless preview endpoints. No surface mixes draft + published within a single render.

## 10. Translation representation

Exactly one canonical representation, owned by **ADR-PB-TRANSLATABLE-FIELD-REPRESENTATION**:

- **Canonical:** per-locale envelope `array<locale, string>` inside `data`.
- **Legacy (interpreted, never rewritten):** plain scalar = source-locale text.
- Resolved by `Localize` under one explicit locale (public URL prefix; editor via
  `RenderLocale::scope`). No array-order fallback, no second representation.

This ADR introduces no new representation and no migration.

## 11. Runtime metadata policy

One rule: **transient/runtime state is never persisted and never rendered into the payload.**

- selection, hover, geometry, overlay, history, clipboard, dirty, autosave, drag state →
  held in the client store and server-side `BuilderState` sub-states, **alongside** the
  payload, never merged into it.
- editor render markers (`pb-editor-render-marker`, `data-pb-component-id`) → produced by
  `renderForEditor` in HTML only; public `render()` emits none.
- temporary ids → replaced by stable `ComponentId::assign` before persistence.
- the normalizer discards any unknown node-level key on save.

Guarded: transient node keys are stripped on save; markers never appear in a stored payload;
publication state is not a column.

## 12. Mutation rules (matrix)

| Mutation | Input | Authority | Output row | Response row | Failure | Rollback |
|---|---|---|---|---|---|---|
| Save Draft | posted payload | `LayoutStore::saveDraft` | effective owner `draft_payload` | same row (`summarize`) | fail-closed owner resolution / normalize drops bad nodes | client keeps dirty; re-save |
| Publish | (no payload) | `LayoutStore::publish` | copy draft → `published_payload` | same row | serialized after save; abort on failed save (2P) | re-publish |
| Render Source set | source enum + locale | `RenderSourceWriter::setForCanonical` (fail-closed) | `editor_mode` | — | throws on bad locale (INV-PB-OWNER-05) | re-set |
| Clone / Provision | effective translation owner | LT clone → `LayoutStore::saveDraft` + `setEditorMode` | translation `draft_payload` (+ builder mode) | provision result | idempotent; fail-closed owner | re-open |
| Baseline capture | target payload | `LayoutBaselineCaptureService` → `LayoutStore::saveDraft` | translation `draft_payload` (stamped) | — | bounded | re-capture |
| Translation sync | source/target | LT coordinator → `LayoutStore::saveDraft` | translation `draft_payload` | sync state | fault-typed | replay |
| Future Revision / Rollback | snapshot | (future) → `LayoutStore` | `draft_payload` / `published_payload` | — | must reuse LayoutStore | see §13 |

All persisted writes route through `LayoutStore`. Provisioning/clone/baseline/sync write the
**draft** of the translation owner only — publishing a translation is a separate explicit
publish.

## 13. Future compatibility

- **Revision / Autosave / Rollback / Schedule Publish:** add snapshot rows/columns and write
  them **through `LayoutStore`**; publication state stays derived; a rollback restores a
  snapshot into `draft_payload` then publishes via `publish()`. No second payload authority.
- **Reusable / Global Blocks:** stored as their own owner's payload; referenced by id;
  resolved at render — never inlined into another owner's `published_payload` as a hidden copy.
- **Theme / Landing / AI Builder, Ecommerce Landing:** new owner types plug into
  `EditableOwners` + reuse the same payload contract, lifecycle, and render authority.

No implementation is authorized here.

## 14. Architecture guards

`PageBuilderDataArchitectureGuardTest`: single column writer (fs scan), publish is a byte
copy, frontend reads published-not-draft, preview reads posted-not-published, transient node
metadata stripped on save, publication state is not a column, markers never persist. Companion
suites: `PageRenderSourceTest`, `PageBuilderLocaleAwareRuntimeTest`, `PageBuilderPublicationStateTest`,
`PageBuilderWholeLayoutPublishTest`, `PageBuilderEffectiveOwnerResolutionTest`.

## 15. Rejected alternatives (forbidden patterns)

- Multiple payload authorities / a second store.
- Editor writing `published_payload`; frontend reading `draft_payload`; preview reading stored `published_payload`.
- Component / Blade / runtime mutating the payload.
- Two translation representations.
- Two Render Source authorities.
- Client-authoritative payload on publish, or client-authoritative publish state.
- Hidden synchronization / implicit payload merge.
- Persisting publication state or runtime/editor metadata.

Each is covered by a guard or by an already-frozen ADR.

## 16. Consequences

- One store, one publish pipeline, one render pipeline, one translation representation, one
  runtime-metadata policy — locked by tests.
- No production change was required: the audit found every authority already single. This ADR
  + guards are the deliverable.
- Future data features inherit the contract by construction or fail the guards.

## 17. Acceptance checklist

- [x] One source of truth (`LayoutStore`).
- [x] One payload authority; single column writer (guarded).
- [x] One publish pipeline (draft → byte-copy → published).
- [x] One render pipeline; frontend never reads draft; preview never reads stored published.
- [x] One translation representation (envelope; ADR-PB-TRANSLATABLE-FIELD-REPRESENTATION).
- [x] One runtime-metadata policy; transient state never persists (guarded).
- [x] One Render Source authority (`RenderSourceWriter`, fail-closed).
- [x] One mutation contract (all writes via `LayoutStore`).
- [x] Publication state derived, never a column (guarded).
- [x] Future-compatible with Revisions (§13).
- [x] No Core / schema / migration change.
- [ ] Live EN/VI hash acceptance (Work N) — pending a booted admin.

Flip Status to **Accepted** once the final box is checked.
