# Design Capture Pipeline (Save as Design)

_Phase 26.48 — capture reusable Builder Resources from the Builder into the Design
Repository through ONE shared pipeline._

Users save a selected section/block as a reusable design. Every future feature
(My Designs, Community, Marketplace, PRO, Portable Package) reuses this single
pipeline — there is never a second save path.

```
Builder → Selection → Capture Pipeline → Normalize → Validate → Fingerprint
        → Preview → Metadata → Persist → repository refresh → registry refresh
```

## Core principle

The Builder creates reusable resources; the **Repository owns them**. The Builder
never writes to the repository directly — everything passes through the
`DesignCapturePipeline`, the only writer into the `my-designs` source (Rule 11).
Saving is an **external persistence action**: it is a plain endpoint, not a
CommandBus command, so it never appears in undo/redo/history and never mutates
BuilderState (Rules 13/20).

## The pipeline (`src/Capture/`)

| Component | Responsibility |
| --- | --- |
| `DesignCapturePipeline` | Orchestrates capture → normalize → validate → fingerprint → duplicate → preview → metadata → persist → refresh → event |
| `DesignCaptureNormalizer` | Strips runtime ids + editor state; wraps a block in a neutral host section; deterministic id-free payload (Rule 6) |
| `DesignCaptureValidator` | Rejects empty / unsupported root / broken component / missing definition / recursive / invalid bindings before persistence (Rule 7) |
| `DesignFingerprint` | Deterministic `sha256` of the normalized JSON only (Rule 8) |
| `DesignPreviewGenerator` | Records the rid-keyed Runtime Preview route as the resource preview (Rule 4) |
| `DesignCaptureResult` | Immutable outcome: saved / duplicate / invalid |

The pipeline contains **no UI logic and no repository query logic** (Rule 5): it
persists to `UserDesignStore` (a file store mirroring `ImportedPackageStore`), then
calls `repository->refresh()` and `registry->refresh()`.

## Normalization (Rule 6)

A captured node carries only `{type, data, blocks}`. Normalization reuses the
package layer's `DesignPackagePayload::stripIds` to remove every runtime id
(section, blocks, nested container blocks). A block is wrapped in a neutral
`generic` host section so it is renderable at the document root (mirroring built-in
block resources). Output is deterministic for a given input.

## Validation (Rule 7)

Reuses the package boundary scanners: unknown component types (against the
Section/Block registries; the `generic` host is always valid), forbidden storage
references and unsafe asset URLs (`invalid_binding`), plus an empty check, an
unsupported-root check, a malformed-node check, and a depth guard
(`MAX_DEPTH = 20`) for recursive-looking structures. Validation always runs
**before** persistence.

## Fingerprint (Rule 8)

`sha256-<hash>` of `DesignPackageManifest::canonicalJson(normalizedPayload)` —
computed from the normalized Builder JSON **only**. It never includes timestamps,
preview, runtime ids, metadata, author, or a media checksum, so the same design
always fingerprints the same (duplicate detection; a stable handle for future
updates/distribution).

## Duplicate handling (Rule 9)

If the fingerprint already exists in the store the pipeline returns `duplicate`
with the existing rid; the dialog shows **Resource already exists** with **Cancel**
and **Create Copy** (which re-runs with `forceCopy`, producing a new resource id
with the same fingerprint). **Update Existing** is reserved (rendered disabled).

## Metadata (Rule 10)

Persisted per record: name, description, category, tags, version (`1.0.0`), author
(the authenticated user, server-side — never exposed to the Builder, Rule 17),
compatibility, fingerprint, source (`my-designs`), preview, `createdAt`,
`updatedAt`, and the normalized payload. **No Builder runtime state is ever
persisted.** Categories reuse the existing model (Rule 15); availability inherits
the FREE default (Rule 14).

## Repository & registry refresh (Rules 11, 12)

The `UserDesignProvider` (source `my-designs`) is registered with the
**repository**, so a captured design is queryable the instant it is persisted, and
`repository->refresh()` + `registry->refresh()` invalidate the caches without
reloading the Builder. The dedicated **My Designs palette group stays reserved**
(Rule 18): no My Designs provider is registered with the `BuilderResourceRegistry`,
so nothing new appears in the Builder palette yet.

## Events (Rule 19)

A single `DesignResourceCaptured` event is dispatched on success (rid, fingerprint,
kind, author). No listeners ship in this phase.

## Editor UX

- **Save as Design** appears on the canvas node toolbar and in the right-panel
  Inspector for a selected section/block (Rule 2).
- `SaveAsDesignDialog` (`resources/editor/capture/`) shows a live preview
  (`DesignPreviewPane`, reusing the existing render pipeline) and the metadata form
  (`DesignMetadataForm`: name/description/category/tags; version + visibility
  fixed). It posts to `POST /api/page-builder/builder/capture` via
  `ApiClient.captureDesign`, branching on saved / duplicate / invalid. On success
  it **only closes the dialog** — selection, history, and undo are untouched.

## Files

**New (PHP):** `src/Capture/{DesignCapturePipeline,DesignCaptureNormalizer,DesignCaptureValidator,DesignFingerprint,DesignPreviewGenerator,DesignCaptureResult,UserDesignStore}.php`, `src/Capture/Events/DesignResourceCaptured.php`, `src/Repository/Providers/UserDesignProvider.php`, `src/Http/Controllers/DesignCaptureController.php`.

**New (JS):** `resources/editor/capture/{SaveAsDesignDialog,DesignMetadataForm,DesignPreviewPane}.vue`.

**Modified:** `routes/api.php` (capture route), `PageBuilderServiceProvider.php`
(bindings + repository provider), editor `types.ts`, `api/client.ts`,
`stores/editor.ts` (capture intent), `components/{CanvasNodeToolbar,EditorRightPanel}.vue`,
`App.vue`.

## What did not change

Builder runtime, the Builder Resource Registry, the Repository contracts, the
insertion commands, BuilderState, the CommandBus, Runtime Preview, the Inspector,
Package Sharing, the Page Template workflow, and the Extension API — all unchanged.
No Export / Portable Package / Community / Marketplace / PRO.
