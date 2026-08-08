# LT-0 — Storage & Identity Audit

**Status: AUDIT (LT-0). No production code, schema, runtime, or UI was changed.**

Source-verified audit of Page Builder layout storage, document schema, node identity, revision/baseline
options, and the canonical mutation pipeline. All cites are relative to `plugins/page-builder` unless
prefixed with `packages/`. Companion: [`LT-0-CORE-ADOPTION-AUDIT.md`](./LT-0-CORE-ADOPTION-AUDIT.md),
[`LT-0-SYNCHRONIZATION-ADR.md`](./LT-0-SYNCHRONIZATION-ADR.md).

---

## 1. Layout storage

Single table, single migration: `database/migrations/2026_07_06_000001_create_pb_layouts_table.php`.
A full-plugin search found **no other migration or table**.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | surrogate |
| `owner_type` | string | content **type string** `'page'`/`'post'` (not a class name) |
| `owner_id` | unsigned bigint | `cms_contents.id` (no FK) |
| `editor_mode` | string | `'classic'` (default) / `'builder'` |
| `draft_payload` | json (cast array) | structured tree, never HTML |
| `published_payload` | json (cast array) | structured tree, never HTML |
| `draft_updated_by`, `published_by` | string nullable | lightweight audit |
| `created_at`, `updated_at` | timestamps | |

`$table->unique(['owner_type', 'owner_id'])` (migration `:46`) → **exactly one row per owner**.
**No `locale` column. No soft-delete. No archive table.** Model: `src/Models/PageBuilderLayout.php`
(casts `:46-49`, `owner(): MorphTo` `:58-61`, `hasPublished()` `:74-77`).

Publishing is a shallow copy `published_payload = draft_payload` in the same row
(`src/Support/LayoutStore.php:97`). **No history row is written.**

---

## 2. Document schema

Canonical normalized shape (`src/Support/LayoutNormalizer.php:50-54`):
```json
{ "schema": "pagebuilder/01", "sections": [ { "type": "...", "id": "...", "data": {}, "blocks": [] } ] }
```
- Schema version field: `payload['schema'] = LayoutNormalizer::SCHEMA = 'pagebuilder/01'`
  (`LayoutNormalizer.php:30`); import checks it (`FullPageImportService.php:78`).
- Section node keys: `{type, id, data, blocks}` (`:79-86`). Block node keys: `{type, id, data}`
  (`:117-122`). **Container blocks nest children at `data.blocks`**, not top-level `blocks` (`:107-116`).
- The normalizer **strips every unknown top-level node key** — only `type|id|data|blocks` survive.
- **`data` is an allowlist-free passthrough** (`data()` returns `node['data']` unchanged, `:170-173`).
  There is no `data`-key validator in `LayoutStore::saveDraft`, `DesignCaptureValidator`, or
  `PatternValidator`.

> **Consequence for correspondence metadata:** a new top-level node key (e.g. `origin_node_id`) would
> be **silently dropped** by the normalizer. Correspondence metadata **must live inside `data`**
> (e.g. `data['_origin']`), where it is preserved verbatim.

---

## 3. Node identity — the central mechanic

Identity field is a single string **`id`** (no `uid`/`key`).

- Editor ids: `pb-<ulid>` (`src/State/ComponentId.php:29-32`).
- Import ids: `imp<hex>-<n>` counter (`src/Import/NodeIdGenerator.php:25,46`).
- **Ids survive save→load**: the normalizer preserves existing ids; `ComponentId::assign()` only fills
  in *missing* ids (`ComponentId.php:112-117`, force=false).
- **Clone/duplicate/paste/import REGENERATE ids**: `ComponentId::regenerate()` (force=true,
  `ComponentId.php:63-66`) and `NodeIdGenerator::assign()` (`NodeIdGenerator.php:23-38`) assign fresh
  ids to a node and every descendant.
- Nested ids are stable and recursed (`ComponentId::assignNode` `:112-146`).
- Captured design resources are stored **id-free** (`DesignPackagePayload::stripIds`), re-id'd on insert.
- **No cross-document / origin correspondence concept exists anywhere** (searched `src/**` for
  `origin_node_id`, `origin_id`, `source_id`, `correspond*` → none).

**Verified for the ADR:** both id-regenerators rewrite **only** the `id` key and recurse into
`blocks`/`data.blocks`; every other `data` key is preserved (`NodeIdGenerator.php:44-69`,
`ComponentId.php:112-146`). Therefore correspondence metadata parked in `data` **survives clone-time
id regeneration** — exactly the property Layout Translation needs (new physical `id`, stable origin id).

---

## 4. Revision / baseline options

| Artifact | Immutable? | Persisted? | Scope | Cite |
|---|---|---|---|---|
| `published_payload` column | no (overwritten on re-publish) | yes | page | `LayoutStore.php:97` |
| `HistoryState` undo/redo | ephemeral | no (session, cap 50) | editor | `src/State/HistoryState.php:18,35` |
| `revisionOf()` / `planHash` | computed transient sha1 | no | import guard | `src/Import/FullPageImportService.php:580-583,534-541` |
| `DesignFingerprint` | **yes (content-addressed)** | yes (in captured-design files) | design resource | `src/Capture/DesignFingerprint.php:25-28` |
| Community `PublishingHistory` | append-only | yes (lifecycle.json) | community resource | `src/Community/Publishing/PublishingHistory.php` |

**Finding:** there are **no immutable revision rows for page drafts**. The only reusable
content-addressing primitive is **`DesignFingerprint`** = `sha256(DesignPackageManifest::canonicalJson
(payload))` (lexicographic key sort, id-free by convention). This is the mechanism the ADR reuses for a
**plugin-owned source baseline**, canonicalized with physical `id`s **and** correspondence metadata
stripped so source and target hash comparably.

---

## 5. Canonical mutation pipeline (what LT reuses)

**Two independent persistence domains:** (a) `pb_layouts` via **`LayoutStore`**; (b) design resources
via file stores (`UserDesignStore`, `ImportedPackageStore`). Layout Translation lives entirely in (a).

**Single canonical layout write authority:** `LayoutStore::saveDraft()`
(`src/Support/LayoutStore.php:71-84`). Every layout-writing path funnels here:

| Operation | Path to the write | Transaction |
|---|---|---|
| Editor save | `EditorContextController::saveDraft` → normalize → `LayoutStore::saveDraft` | none |
| Publish | `EditorContextController::publish` → `setEditorMode` + `LayoutStore::publish` (copy draft→published) | none |
| Apply template (new/existing) | `TemplateApplicationService` → `FullPageImportService::import` → normalize → `NodeIdGenerator::assign` → `LayoutStore::saveDraft` | **`DB::transaction`** (`FullPageImportService.php:136`) |
| Direct import commit | `FullPageImportController` → `FullPageImportService::import` | **`DB::transaction`** |
| In-editor duplicate component | `CommandBus` → `DuplicateComponentCommand` → `LayoutMutator::duplicateComponent` (`ComponentId::regenerate`) | in-memory only, persisted later via saveDraft |
| Preview (both controllers) | read-only, no write | — |

**Normalization/validation/fingerprinting happen in the caller, not in `LayoutStore`.**
`LayoutStore` is a pure persistence gateway.

**The reference pattern for "clone source layout into a new target" is
`FullPageImportService::import()`:** inside `DB::transaction` → load source payload → `normalize()` →
`NodeIdGenerator::assign()` (fresh physical ids) → `LayoutStore::saveDraft(target)` → verify. Layout
Translation's clone (LT-3) reuses exactly this shape, adding the correspondence stamp and baseline
record.

---

## 6. The per-locale binding gap (LT-2)

Because `owner_id = cms_contents.id` and **one `Content` row spans all locales**
(see [`LT-0-CORE-ADOPTION-AUDIT.md`](./LT-0-CORE-ADOPTION-AUDIT.md) §4), the `unique(owner_type,
owner_id)` constraint means **one `pb_layouts` row is shared by every locale** — the layout is
structurally locale-blind. To give each locale its own independently-editable structure, the layout
must bind to `(owner_type, owner_id, locale)`, congruent with the Core `(content_id, locale)`
translation set.

Two additive, plugin-owned options (decision deferred to LT-2, evaluated in the ADR):

- **Option A — `locale` column on `pb_layouts`**, unique `(owner_type, owner_id, locale)`; backfill the
  single existing row as the default locale. Smallest surface; touches the existing hot table.
- **Option B — new `pb_layout_translations` table** keyed `(owner_type, owner_id, locale)`, leaving
  `pb_layouts` as the default-locale row untouched. Cleaner isolation; more plumbing.

Either way, the default-locale layout and all current single-language behavior must remain
**byte-identical** (the LT-1+2 gate). No schema is introduced in LT-0.
