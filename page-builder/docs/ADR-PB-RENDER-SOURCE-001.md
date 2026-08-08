# ADR-PB-RENDER-SOURCE-001 — Classic Content vs Page Builder Content

**Status:** Accepted (architecture lock — no implementation authorized by this ADR)
**Date:** 2026-08-03
**Phase:** PB-RS-0 (ADR-first) → corrected by **PB-RS-0A** (Render Source domain lock & ADR correction)
**Supersedes:** nothing. **Amends:** nothing. **Depends on (frozen):** Core Localization Platform (P4C), Layout Translation LT-10, LT-M1.

> **PB-RS-0A correction (domain model).** The original draft of this ADR declared
> `pb_layouts.editor_mode` to be "the render authority". That conflated two
> distinct concepts. This revision corrects it:
>
> ```text
> Editor Mode  ≠  Render Source
>
> Render Source is the domain authority.
> pb_layouts.editor_mode is the current legacy storage representation.
> Storage does not define the domain concept.
> ```
>
> The column is **not** renamed, no migration is created, and no stored value
> changes. Storage evolution (physical separation of the two concepts) is a
> future *additive* change that requires a real product need and its own
> ADR + migration; it is **not** pre-built here.

---

## Context

Page Builder Translation (LT-M1) is functionally stable. Before adding any new
Rendering UI or runtime, we lock the single authoritative **render source** for
every Page / Post / Custom Post Type (CPT), per effective owner, without
redesigning Page Builder, Translation, or Core.

The PB-RS-0 audit found the *storage* and the *public switch* already exist and
are already wired. PB-RS-0A additionally found the codebase currently uses one
raw field (`editor_mode`) to mean two things (how content is edited **and** what
the public renders), and that the method name `setEditorMode` is overloaded
across two unrelated concepts. This ADR ratifies **Render Source** as the domain
authority and names the conflation as a documented, deferred separation point.

---

## Two distinct concepts

### Editor Mode — *how content is currently edited*

```text
Classic Editor
Page Builder Editor
```

### Render Source — *which content source the public frontend renders*

```text
Classic Content
Page Builder Content
```

They currently map one-to-one in storage, but they are **not** semantically
identical. These future scenarios must remain expressible and MUST NOT be
blocked by terminology or contracts:

```text
Edit with Page Builder   →   Public Render Source = Classic Content
Edit with Classic Editor →   Public Render Source = Page Builder Content
```

> **Naming-collision note.** `ComponentRuntime::setEditorMode(bool)` (consumed by
> `LayoutRenderer` and `PreviewEngine`) is a THIRD, unrelated thing: a
> render-with-editor-markers toggle. It is neither Editor Mode nor Render Source.
> New code MUST NOT reuse that method name for Render Source.

---

## Decision

### 1. Ownership

- **Core owns:** classic content (`content` + `content_translations`), the classic
  editor, translation identity/lifecycle, the public render pipeline, and the
  `cms.content.body` filter seam.
- **Page Builder owns:** layout documents (`pb_layouts`: `draft_payload` /
  `published_payload`), the builder runtime, and the **Render Source** decision
  (currently persisted in `pb_layouts.editor_mode`).

Page Builder never modifies Core. It participates purely through the existing
`add_filter('cms.content.body', …)` seam in
`PageBuilderServiceProvider::registerFrontendRender()`.

### 2. Render authority — exactly one

**Render Source is the architectural authority.** The current implementation
persists it through the existing `editor_mode` field until storage evolves.
It is read on the public path by exactly one switch — `FrontendRenderer::bodyFor()`
— and on the editor-iframe path by `FrontendEditorRenderer`. No other component
decides render source.

The authority is evaluated **per effective owner**, never globally. The effective
owner is resolved by the frozen `EffectiveEditorOwnerResolver`
(`resolveForReadOrCanonical`), so each `(owner_type, owner_id)` row — including
per-locale `content_translation` rows — carries its own Render Source.

### 3. Render Source values — exactly two

```text
RenderSource::Classic   ↔   editor_mode = 'classic'   (default)
RenderSource::Builder   ↔   editor_mode = 'builder'
```

**No Auto.** (`LayoutTranslationSetting`'s Auto/Enabled/Disabled is a *different*
axis — the Translation feature flag — and is out of scope.)

### 4. Domain representation (specification — landed in PB-RS-1, not now)

The smallest immutable representation, with no container/persistence/query/
render/workflow logic, failing closed to Classic on corrupt or unknown storage:

```php
enum RenderSource: string
{
    case Classic = 'classic';
    case Builder = 'builder';

    /** Map from the legacy editor_mode storage. Unknown/null → Classic (fail closed). */
    public static function fromStorage(?string $value): self
    {
        return $value === self::Builder->value ? self::Builder : self::Classic;
    }

    /** The current storage representation for this source. */
    public function toStorage(): string
    {
        return $this->value;
    }
}
```

Backed-enum values equal the current `editor_mode` strings, so the mapping is
identity at the boundary and no data changes. Preferred consumption is an
accessor at the storage/domain boundary — never raw string compares scattered
through controllers / Vue / Blade / renderers:

```php
// PageBuilderLayout
public function renderSource(): RenderSource
{
    return RenderSource::fromStorage($this->editor_mode);
}
```

`isPageBuilderMode()` and the `MODE_*` constants remain for legacy compatibility;
they become thin aliases of `renderSource()` over time (no broad refactor now).

### 5. Runtime

```
Classic  →  Core classic content (the unmodified $classicBody)
Builder  →  pb_layouts.published_payload  (never draft on the public path)
```

Fail-soft: if Render Source is Builder but there is **no** published payload
(`hasPublished() === false`), or the payload render fails / is empty, the public
render returns the **Classic Content** body. Invariants already enforced in
`FrontendRenderer::bodyFor()`:

- **No automatic publish** during render.
- **No automatic cloning** during render (`resolveForReadOrCanonical` never
  provisions/clones/writes).
- **No write of any kind** during render.

### 6. Locale independence

Every locale independently chooses its Render Source, because each effective
owner is a distinct `pb_layouts` row:

```text
EN  → page/1                 → Render Source = Page Builder Content   (legal)
VI  → content_translation/1  → Render Source = Classic Content        (legal)
```

Already supported and proven by `PageBuilderLocaleAwareRuntimeTest`.

### 7. Data preservation

Switching Render Source changes **only** the persisted source pointer. It MUST
NEVER delete, convert, rewrite, or migrate classic content, and MUST NEVER mutate
`draft_payload` / `published_payload`. Both sources remain intact and reversible;
only which one the public renders changes.

### 8. Preview

Preview follows the selected Render Source through the **same**
`cms.content.body` authority:

- **Builder in-editor preview** (`?pb_editor=1` + `page-builder.edit`) →
  `FrontendEditorRenderer` renders the **draft** with editor markers.
- **Public / signed preview** (Core `PreviewManager` →
  `FrontendController::renderPreviewable()` → `apply_filters('cms.content.body')`)
  → published Builder payload, else fail-soft to Classic. The public path never
  renders a Builder draft.

### 9. Core boundary

No Core modification. No Core render-hook change. No translation redesign. Page
Builder stays a pure consumer of Core's `cms.content.body` filter and Core's
localization / effective-owner contracts.

---

## Editor-Mode usage audit (PB-RS-0A Work E)

Every current `pb_layouts.editor_mode` touch, classified. (`ComponentRuntime`'s
boolean `setEditorMode`/`editorMode` are the marker-toggle concept and are listed
separately as a naming collision — NOT render source.)

| Location | Use | Classification |
|---|---|---|
| `Models/PageBuilderLayout.php` (`MODE_*`, `editor_mode`, `isPageBuilderMode()`, migration default) | column + read | **RENDER_SOURCE_STORAGE** (named legacy-style; the domain concept's storage) |
| `database/migrations/…create_pb_layouts_table.php` | column def | **RENDER_SOURCE_STORAGE** (physical; unchanged) |
| `Support/LayoutStore.php` (`setEditorMode`, `isPageBuilderMode`) | storage gateway | **RENDER_SOURCE_STORAGE** (the boundary where the mapping belongs) |
| `Support/FrontendRenderer.php:65` (`isPageBuilderMode()`) | public switch | **RENDER_SOURCE_STORAGE** (the one public authority read) |
| `Support/FrontendEditorRenderer.php:140` (`isPageBuilderMode()`) | editor canvas | **RENDER_SOURCE_STORAGE** (read for the draft canvas) |
| `LayoutTranslation/Cloning/Adapters/PageBuilderTargetLayoutWriter.php:31` (clone → `MODE_BUILDER`) | LT clone write | **RENDER_SOURCE_STORAGE** (frozen LT contract — untouched) |
| `Support/EditorContext.php:77,140,168` (`editor_mode` in boot payload) | boot → Vue | **AMBIGUOUS** (render-source value used to drive editor UX display) |
| `Http/Controllers/EditorContextController.php:95` (opening context writes `MODE_BUILDER`) | editor action → storage write | **AMBIGUOUS** (an EDITOR action writes the RENDER SOURCE — the core conflation) |
| `Filament/Pages/PageBuilderFrontendEditorPage.php:54,117-119,198-214` (`$editorMode`, `setMode`, "Use Classic Editor") | editor page UI + write | **AMBIGUOUS** (editor-labelled action mutates public render source) |
| `Filament/Pages/PageBuilderEditorPage.php:52,178-219` ("Use Page Builder"/"Use Classic Editor" → `setMode`) | editor page UI + write | **AMBIGUOUS** (editor-labelled action mutates public render source) |
| `Filament/Forms/EditorLauncherPanel.php:47,79-81` (`savedMode`, localStorage segmented control) | display preference | **EDITOR_BEHAVIOR** (display sourced from render-source storage; write is non-authoritative localStorage) |
| `Support/LayoutRenderer.php:77,82,116,121`, `Runtime/ComponentRuntime.php:38,43`, `Preview/PreviewEngine.php:63` (`setEditorMode(bool)`) | marker toggle | **EDITOR_BEHAVIOR** — *naming collision only*, NOT render source |

**Where the same raw field drives both editor UX and public rendering:** the
AMBIGUOUS rows — `EditorContextController` (entering the Builder writes Builder
render source), the two editor Pages' `setMode` actions, and the `EditorContext`
boot payload. Today "open/enter the Builder editor" implies "public Render Source
= Builder". **These paths are NOT redesigned in this phase.** Physical/semantic
separation of Editor Mode from Render Source is a **future additive change that
requires a real product need and a separate ADR + migration.**

---

## Consequences

- The forthcoming **Rendering** control (radio: Classic / Page Builder) is a
  **UI surface over the existing authority**: it reads `renderSource()` and writes
  it through `LayoutStore::setEditorMode(effectiveOwnerType, effectiveOwnerId, …)`
  (the mapping stays at that boundary), targeting the effective owner for the
  editing locale — the pattern `PageBuilderFrontendEditorPage::setMode()` uses.
- CPT support is a **registry extension** (`EditableOwners` + its `editUrl` map;
  auto-registered into `LayoutOwnerRegistry`), not new logic. Render/preview/write
  code is already generic over `owner_type`.
- No schema, migration, Core change, translation change, or render redesign is
  required.

---

## Locked statements

1. Core owns Classic content.
2. Page Builder owns layout documents.
3. Editor Mode and Render Source are different concepts.
4. Render Source is the architectural authority.
5. `editor_mode` is only the current storage representation.
6. Render Source is selected per effective owner.
7. Switching source preserves both content sources.
8. Public Builder rendering uses the published payload only.
9. Missing published Builder payload fails soft to Classic.
10. Preview may use a Builder draft; the public frontend may not.
11. Frontend rendering never writes.
12. No Core modification.

---

## Admin terminology (locked)

Label: **Rendering** (or **Render Source**). Never "Editor Mode" or "Content Display".

```text
Rendering
  ○ Classic Content
  ○ Page Builder Content

Help: Choose which content source the public page renders.
      Switching the source does not delete or convert either Classic or Page Builder content.

When Builder is selected but not published:
  A Page Builder draft exists, but it has not been published.
  The public page currently falls back to Classic Content.
```

---

## Implementation sequence (no big-bang)

- **PB-RS-1 — Page only:** `RenderSource` representation + storage mapping +
  boundary accessor; effective-owner write seam; the Page "Rendering" control;
  frontend/preview tests.
- **PB-RS-2 — Post:** after Page is stable, via the same seam.
- **PB-RS-3 — CPT:** through the existing owner registry (`EditableOwners` +
  `editUrl`), no logic change.

Page, Post, and CPT are **not** implemented simultaneously.

---

## Invariants (guarded by tests when the feature is built)

- **INV-RS-01** — Render Source has exactly two values; `Classic ↔ 'classic'`, `Builder ↔ 'builder'`; default `Classic`.
- **INV-RS-02** — Corrupt/unknown storage fails closed to `Classic`.
- **INV-RS-03** — Public render source is decided ONLY in `FrontendRenderer::bodyFor()`.
- **INV-RS-04** — Builder public render uses `published_payload` only, never `draft_payload`.
- **INV-RS-05** — Builder without a published payload fails soft to Classic.
- **INV-RS-06** — Render performs no write / publish / clone.
- **INV-RS-07** — Render Source is per effective owner (per locale); switching one locale never affects another.
- **INV-RS-08** — Switching Render Source never mutates classic content or builder payloads.
- **INV-RS-09** — Preview shares the same authority (draft only under `pb_editor` + permission).
- **INV-RS-10** — Editor Mode and Render Source are not presented as the same concept (terminology + accessor).
```
