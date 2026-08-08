# Runtime Preview Engine (Phase 26.41)

> **Status:** Implemented. Runtime-safe, read-only. Reuses the production render
> pipeline verbatim — it does **not** fork the renderer, the serialization, the
> `BuilderState`/`CommandBus`, or the Extension API.
>
> This is the first implementation phase of the Design Library preview described in
> [design-preview-architecture.md](design-preview-architecture.md) (26.40A) and
> [design-preview-specification.md](design-preview-specification.md) (26.40B).

---

## 1. What it does

The Runtime Preview Engine renders a **Design Resource** (a page, a section, or a
block) into a self-contained, sandboxed HTML document for display in a read-only
iframe. Because it renders through the **exact production pipeline**, the preview is
visually identical to what inserting the resource would produce.

It is a **projection**, not an edit:

- no `insert-pattern` command, no undo entry, no dirty flag;
- it never reads the current page's content or the editor's `BuilderState`;
- it renders only the resource tree carried by the `PreviewContext`.

---

## 2. Architecture

```
PreviewController (GET, admin)                 PreviewEngine::preview(PreviewContext)
        │  resolve source→context                        │
        ▼                                                 ▼
PreviewResourceResolver ── PreviewContext ──▶ PreviewCache.remember(key)
   sample | section:<t> | block:<t>            │  (in-memory, per-request)
                                               ▼
                         SampleContentResolver.apply(tree)   ← fill empty fields
                                               │
                         columnWidths.reset(); dynamicStyles.reset()
                         runtime.setEditorMode(false)
                                               │
                         PageBuilderManager::render($payload)   ← PRODUCTION render
                                               │
                         PageBuilderFrontendAssets::headHtml()  ← same asset head
                                               ▼
                                       PreviewResponse {head, body, device, width}
                                               │
                         view('page-builder::preview.document') + strict CSP
```

### Key reuse points (no duplication)

| Concern | Reused production API |
|---|---|
| Render tree → HTML | `PageBuilderManager::render(?array $payload): string` (→ `LayoutRenderer` → `ComponentRuntime`) |
| Runtime CSS + stylesheet | `PageBuilderFrontendAssets::headHtml()` (tokens + column widths + dynamic styles + `<link>`) |
| Collector reset (mirrors `FrontendRenderer`) | `ColumnWidthRegistry::reset()`, `DynamicStyleRegistry::reset()` |
| URL safety | `Support\Html::safeUrl()` policy (http/https/relative only) |
| Breakpoints | mirror `TokenRegistry` (desktop 1280 / tablet 768 / mobile 480) |

---

## 3. Classes (`src/Preview/`)

| Class | Responsibility |
|---|---|
| `PreviewContext` | Immutable description of *what* to preview: kind, id-free `LayoutPayload`, device, locale, sample-content flag. Provides a deterministic `cacheKey()`. |
| `PreviewResourceResolver` | Turns a `(source, reference)` request into a `PreviewContext`. Sources: `sample`, `section:<type>`, `block:<type>`. Seam for a future Design-Library RID lookup. |
| `SampleContentResolver` | Deterministically fills empty, well-known fields (headings, body, labels, images…) so sparse resources preview realistically. Never overwrites authored content. |
| `DevicePresets` | Device → viewport width (mirrors breakpoint tokens). Switching device changes width only. |
| `PreviewEngine` | Orchestrates: sample-fill → reset collectors → production render → asset head → `PreviewResponse`. Memoises via `PreviewCache`. |
| `PreviewCache` | In-memory, per-request cache of `PreviewResponse`. **No persistent storage.** |
| `PreviewResponse` | Immutable result: `head`, `body`, `device`, `width`, `locale`, `hash`. |
| `PreviewResourceException` | Unknown source / unknown type / missing reference → mapped to 404. |

Controller: `Http\Controllers\PreviewController`. View:
`resources/views/preview/document.blade.php`.

---

## 4. Endpoint

```
GET  {admin_prefix}/page-builder/preview
     route name: page-builder.preview
```

Query parameters:

| Param | Values | Default | Meaning |
|---|---|---|---|
| `source` | `sample` \| `section` \| `block` | `sample` | Which resource source to preview |
| `ref` | component type KEY | — | Required for `section`/`block` (e.g. `hero`, `heading`) |
| `device` | `desktop` \| `tablet` \| `mobile` | `desktop` | Viewport width |
| `sample` | boolean | `true` | Fill empty fields with sample content |
| `locale` | locale code | app locale | Display locale |

Auth: `cms_can('page-builder.edit')` (403 otherwise). Unknown source/type → 404.

Example iframe usage:

```html
<iframe
    src="/page-builder/preview?source=section&ref=hero&device=mobile"
    sandbox="allow-same-origin"
    title="Preview"></iframe>
```

---

## 5. Determinism

Identical inputs always produce byte-identical output:

- `PreviewContext::cacheKey()` = `sha1` of `{kind, device, locale, sample, payload}`.
- `SampleContentResolver` chooses values by a stable hash of the node path + key —
  **no randomness, no time**.
- No `now()`/`rand()` anywhere in the engine.

This makes previews cacheable and reproducible. The cache (`PreviewCache`) is an
in-memory map that lives for one request only — it introduces no persistent storage.

---

## 6. Device switching

`device` changes only the viewport width used by the preview document
(`max-width` of the canvas + the `viewport` meta). The **resource data is never
altered** — desktop, tablet, and mobile render the same `body` HTML at different
widths.

---

## 7. Security & isolation

The preview document is an inert, read-only viewer:

- **Strict CSP** (set by `PreviewController`):
  `default-src 'none'; img-src 'self' https: data:; style-src 'self' 'unsafe-inline';
  font-src 'self' https: data:; frame-ancestors 'self'; base-uri 'none';
  form-action 'none'`.
  There is **no `script-src`** → scripts cannot execute in the frame.
- Additional headers: `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`.
- No editor chrome, no selection markers, no interactivity.
- All component output is escaped by the existing Blade views (`{{ }}`, `Html::safeUrl`).
  The engine injects no arbitrary Blade and executes only registered component views.
- Sample image fields use safe relative placeholder URLs; unsafe schemes are never
  emitted.

---

## 8. What it does NOT touch (frozen)

- Serialization / document model (`LayoutPayload → SectionNode → BlockNode`).
- `BuilderState`, `CommandBus`, undo/redo.
- The Phase 26.40 Extension API.
- Any persistent storage or migration.

The engine only *reads* through public rendering services. Its render call is the
same one the public frontend uses (`PageBuilderManager::render()` in non-editor mode).

---

## 9. Extending to the Design Library

`PreviewResourceResolver` is the seam. When the Design Library lands PHP-side
resource storage, add a source (e.g. `resource:<rid>`) that loads the resource's
id-free tree and returns a `PreviewContext` — the rest of the pipeline (sample
content, render, assets, cache, document, CSP) is unchanged. Callers may also build a
`PreviewContext` directly from any tree and call `PreviewEngine::preview()`.

---

## 10. Tests

`tests/Feature/Preview/RuntimePreviewTest.php` (10 tests, all green) covers:

- page / section / block preview rendered through the production pipeline;
- runtime parity (same markup + asset head as the frontend);
- device switching changes width only, not content;
- sample-content determinism + non-destructiveness;
- cache reuse;
- unknown source / unknown type → exception (→ 404);
- the preview document is sandboxed (no scripts, no editor markers).

> **Local test note:** the dev marker `storage/app/tncms-installed` makes the CMS
> boot in "installed" mode and eagerly query `cms_settings`. Move it aside while
> running plugin tests locally (CI runs with `TN_CMS_INSTALLED=false`, no marker):
> `mv storage/app/tncms-installed{,.bak}` … run tests … `mv storage/app/tncms-installed{.bak,}`.

Run:

```bash
php artisan test tests/Feature/Preview/RuntimePreviewTest.php
```

---

*End of runtime-preview.md*
