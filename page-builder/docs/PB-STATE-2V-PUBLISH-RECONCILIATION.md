# PB-STATE-2V — Publish Reconciliation & Status Truth

## Symptom

In the Vietnamese Live Builder the bottom-left indicator read **"Draft saved"**, the
editor showed updated Hero content, yet the public VI page kept rendering older Hero
content.

## Root Cause — Class A (Client Orchestration / Status Truth)

The forensic audit (PB-STATE-2U) ruled out the other two classes with hard evidence:

- **Class B (server publish) — DISPROVEN.** `LayoutStore::publish()` copies
  `draft_payload → published_payload` unconditionally for the resolved effective owner,
  and `pb_layouts` row `page/1` was fully reconciled (`draft_hash == published_hash`),
  proving the mechanism works.
- **Class C (render / cache) — DISPROVEN.** No response-cache middleware exists; the
  stored `published_payload` of the VI row was itself older than its `draft_payload`, and
  `FrontendRenderer::bodyFor()` correctly renders only `published_payload`. The frontend
  was faithfully rendering a stale-but-authoritative snapshot.

### Evidence (DB, read-only)

| Row | Owner | `draft == published` | Meaning |
|----|-------|:---:|---------|
| id=1 | `page/1` (EN source) | **YES** | publish reconciles correctly |
| id=2 | `content_translation/1` (VI) | **NO** | VI draft ahead of VI published |

Structural Hero diff on row 2:

| Field | draft (editor) | published (frontend) |
|-------|----------------|----------------------|
| hero.title | `{"vi":"Xây dựng một trang web dễ dàng hơn bao giờ"}` | `"Design that works everywhere"` (source) |
| button #-10 label | `{"vi":"Bắt đầu"}` | `"Learn more"` (source) |

So the VI **draft** carries the real translations; the VI **published** still holds the
cloned source scalars. Publish had not reconciled draft→published for the VI owner after
the latest edits.

### Why the status masked it

`BuilderStatusBar` / `EditorTopBar` derived their label purely from local save flags:

```
saving → "Saving…"; dirty → "Unsaved changes"; last_saved_at → "Draft saved"
```

There was **no `Published` / `Unpublished changes` / `Publish failed` state**, and no
access to publish-truth. Two failure modes both surfaced as the reassuring "Draft saved":

1. A saved draft that was never (re)published — `dirty=false`, `last_saved_at` set.
2. A publish that failed *after* a successful save — `dirty=false`, error shown only in a
   separate notice (`publishAtomicity.store.test.ts` case "publish itself fails").

## Fix (smallest durable change — status/publish lifecycle only)

No change to frontend render authority (still `published_payload`), no change to server
publish copy, no schema/migration, no second source of truth.

1. **Server truth signal.** `EditorContext` now emits `is_published_current` on every
   `show` / `saveDraft` / `publish` response — a byte-stable JSON comparison of the
   id-normalized `draft` vs `published` payloads for the same effective owner.
2. **Store truth.** New `publishFailed` flag (set only when a publish attempt does not
   complete; cleared on a fresh save / boot) and a single `publicationState` getter:
   `saving → publishing → unsaved → publish_failed → published → unpublished → none`.
3. **Status widgets.** Both status bars render `publicationState`:
   - `Unsaved changes` — local un-saved edits.
   - `Unpublished changes` — saved draft not yet reflected in the published snapshot
     (public page stale). *Replaces the old misleading "Draft saved".*
   - `Publishing…` / `Publish failed` — in-flight / failed publish (never a safe fallback).
   - `Published` — draft matches the published snapshot.

The existing publish orchestration was already correct (serialized save→publish, aborts on
failed save, guards duplicate clicks, preserves locale) — PB-STATE-2P; 2V only makes its
outcome *visible and truthful*.

## Data Contract

`show` / `saveDraft` / `publish` response `layout` block (and `summarize`) now include:

```json
{ "is_published_current": true, "has_published": true, "has_draft": true }
```

`is_published_current` is TRUE only when the published snapshot already equals the current
draft for that locale owner.

## Tests

- **PHP** `tests/Feature/PageBuilder/PageBuilderPublicationStateTest.php` (new, 2):
  save→publish→edit lifecycle flips `is_published_current` false→true→false; publishing VI
  never flips EN and never publishes the EN source owner.
- **PHP** `PageBuilderWholeLayoutPublishTest.php` (existing): one publish reconciles the
  whole VI layout; frontend renders published only.
- **TS** `resources/editor/stores/__tests__/publicationState.store.test.ts` (new, 8):
  `publicationState`/`isPublishedCurrent` truth table; a failed publish reads
  `publish_failed` (never `published`); a fresh save clears the failure.
- **TS** `BuilderStatusBar.test.ts` (extended, +3): `Unpublished changes` / `Published`
  (not "Draft saved") / `Publish failed`.

Gates: Vitest **1456/1456**; PB PHPUnit `tests/Feature/PageBuilder` **538/538**; editor +
frontend-shell bundles rebuilt (both contain the new labels).

## Stale row & manual acceptance

The pre-existing stale row `content_translation/1` (draft ahead of published) is reconciled
by **one operator Publish** — not a manual DB copy. With this fix the operator now *sees*
`Unpublished changes` prompting that action, and `Published` once done.

Browser runbook (not automatable this session; conclusions dependent on it are marked
NOT PERFORMED):

1. Open `/{admin}/page-builder/page/1/frontend?locale=vi`; DevTools → Network (preserve).
2. Edit Hero title to a unique marker; Save draft → status reads **Unpublished changes**.
3. Click Publish once → Network shows `POST …/publish?locale=vi` 2xx; status reads
   **Published**.
4. DB: `content_translation/1` `draft_hash == published_hash`.
5. `GET /vi/trang-chu` renders the marker; `GET /en/…` unchanged (EN isolation).

## Deferred (no correctness debt)

- Optional richer affordance (e.g. a "Publish now" hint on the `Unpublished changes`
  badge) — pure UI polish.
- Exposing `draft_hash` / `published_hash` in the API was intentionally **not** added; the
  boolean `is_published_current` is the durable truth signal and avoids new surface.
