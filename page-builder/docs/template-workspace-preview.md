# Page Builder — Template Workspace Assets + Preview

*Phase 26.36 (FREE v1). Completes the admin workspace experience: (A) a **route-scoped Vue bundle**
so the workspace loads only on its Filament page, and (B) a **metadata-only template preview**.
Preview is a presentation capability — it is **not** a renderer, builder, or execution engine, and it
never touches BuilderState, a command, or the runtime.*

```
Filament template page ─▶ workspace.js/css (route-scoped) ─▶ mountTemplateWorkspace('#pb-template-workspace')
                                                                │
                          Card ─ Preview ─▶ modal (image + metadata) ─ Close ─▶ Use ─▶ usage → apply → runtime
```

---

## Part A — Route-scoped asset integration

A **second** Vite bundle, mirroring the editor build and reusing the same infrastructure:

- Entry `resources/editor/workspace.ts` — registers built-in/starter packs (non-critical) and calls
  `mountTemplateWorkspace('#pb-template-workspace')` on `DOMContentLoaded`.
- Config `vite.workspace.config.ts` — IIFE lib build → `public/vendor/page-builder/workspace/workspace.{js,css}`
  (`process.env.NODE_ENV` defined, `cssCodeSplit:false`). Scripts: `build:workspace`, and appended to `build`.
- Blade `filament/pages/template-workspace.blade.php` references `workspace.js` / `workspace.css`
  (versioned via `PageBuilderManager::VERSION`) **only on this page** — never globally, never on the
  editor or public frontend. A "Loading…" fallback shows until the bundle boots.

This keeps editor assets out of the workspace route and avoids loading the workspace anywhere else.

---

## Part B — Preview foundation

Contract (`templatePreview.ts`), metadata-only — URLs, never ids / state / payloads:

```ts
interface TemplatePreview { thumbnail?: string; previewUrl?: string }
```

- `TemplateLibraryItem` gains an optional `previewUrl` (a larger preview image), sanitized through the
  **same asset boundary** as `thumbnail` (`sanitizeThumbnail` → `isSafeAssetUrl`) at registration.
- `toTemplatePreview(item)` re-guards both URLs (defense in depth); `previewImageUrl(item)` returns
  the best safe image (`previewUrl` preferred, else `thumbnail`, else `undefined`).

### UI

- `WorkspaceTemplateCard` gains a **Preview** button (emits `preview`) beside **Use**.
- `TemplatePreviewModal` shows the store's `previewItem`: a larger image (or an initial placeholder
  when there is no safe URL) plus source/category/name/description, with **Close** and **Use**.
- Store: `previewItem` state + `preview(item)` / `closePreview()`.

### Usage is unchanged

Preview never applies. **Use** (from the card or the modal) closes the preview and hands off to the
existing usage → apply → runtime flow — one command, one undo, owned by the runtime.

---

## Boundaries

- **Route-scoped assets** — the workspace bundle loads only on its admin page; no global/editor/frontend load.
- **Preview is presentation only** — no second renderer/builder/execution; no BuilderState, command, or runtime.
- **Safe URLs enforced** — unsafe schemes are dropped; an unsafe preview URL is never surfaced.
- **Sources unchanged** — builtin / local / imported; no new source.
- No live iframe preview, marketplace preview, cloud/screenshot rendering, AI, analytics, or ratings.
