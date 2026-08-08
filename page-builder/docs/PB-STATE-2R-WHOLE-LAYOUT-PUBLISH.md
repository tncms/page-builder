# PB-STATE-2R — Whole-Layout Publish: Reconciliation & Acceptance

## Frozen invariants

- **INV-PB-PUBLISH-01** Publish is whole-layout, never per-element.
- **INV-PB-PUBLISH-02** One `published_payload` is one atomic snapshot of the complete component tree.
- **INV-PB-PUBLISH-03** No element owns an independent draft/published lifecycle.
- **INV-PB-PUBLISH-04** After a successful publish, `draft_payload == published_payload` for the same effective owner at the publish boundary.
- **INV-PB-PUBLISH-05** The public frontend renders only the `published_payload` of the locale's effective owner.
- **INV-PB-PUBLISH-06** Publish flushes pending inspector + inline edits, persists the latest draft, and only then publishes.
- **INV-PB-PUBLISH-07** A successful publish response must not be shown if the current editor state was not persisted and published.

## What PB-STATE-2Q proved, and what PB-STATE-2P fixed

The mixed Vietnamese frontend (some fields new, some old) was **not** per-element publishing. `LayoutStore::publish()` copies the whole `draft_payload → published_payload`, and `FrontendRenderer::bodyFor()` renders that whole snapshot. The mixed output was a **stale whole-layout snapshot**: `published_payload` predated the latest title/subtitle translations, which lived only in `draft_payload`. `Support\Localize::text()` then resolves each translatable envelope per field (`vi` when present, else the first non-empty/source string), so a stale/partial snapshot renders as mixed rather than erroring.

The root cause was the client publish state machine shipping `client.publish()` after an internal `saveDraft()` that could fail/race silently. Fixed in commit **`2a2b871`** (`editor/stores/editor.ts`): `saveDraft()` now returns a success contract and joins any in-flight save; `publish()` serializes flush → save → publish and **aborts** on a failed save (dirty + typed error preserved, never masked).

## Reconciliation policy for pre-fix stale rows

A row where `draft_payload != published_payload` is a **legitimate state**: the page/layout has **unpublished whole-layout changes**. It is reconciled by the normal, corrected workflow — nothing else:

- Show a **page/layout-level** Publish action (never element-level publish controls).
- **One** successful corrected Publish reconciles the complete snapshot (`draft == published`).
- **No** migration silently publishes user drafts.
- **No** automatic publish on plugin upgrade.
- **No** direct DB reconciliation / console `draft → published` copy.

This is operational policy, not a new subsystem.

## Page-level draft/published semantics (user-facing model)

```
draft_payload == published_payload  → no unpublished layout changes
draft_payload != published_payload  → the whole page/layout has unpublished changes
```

Never "Hero is unpublished / Button is published." Any unpublished-changes presentation is the whole layout's, not an element's. (Launcher/badge presentation is the dedicated PB-STATE-2B phase, not this one.)

## Acceptance evidence (this phase)

- **Source fix active:** working tree == HEAD `2a2b871`; `saveDraft():Promise<boolean>`, failed save blocks publish, in-flight join, `persistDraft`, reference-guarded dirty clear, no error-clobber.
- **Served bundle is the fix:** source `2a2b871` → built `frontend-editor/shell.js` SHA-256 `7042e221…228d` → HTTP-served `shell.js` SHA-256 `7042e221…228d` (identical; `Last-Modified` matches the build).
- **Whole-layout reconciliation proven end-to-end:** `tests/Feature/PageBuilder/PageBuilderWholeLayoutPublishTest.php` reproduces the exact stale-VI scenario through the real `EditorContextController` save/publish and proves one corrected publish yields `draft == published` (whole tree, byte-identical), the VI render carries all three translated values and none of the source, EN is untouched, and no per-element publish API exists.
- **Regression:** Vitest 1443/1443; PB PHPUnit feature suite 526/526 (2187 assertions); 4/4 production builds.

## Live row (`content_translation/1`) — operational step

The live stale VI row is reconciled by a single Publish in the VI editor with the now-served 2P bundle — the first *correct* publish, not a workaround or a second click. The corrected server workflow (and its whole-layout guarantee) is proven above; the live click was outside this automated environment's reach.
