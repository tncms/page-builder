# Design Library UI (Phase 26.43)

> **Status:** Implemented (UI foundation). Runtime-safe. Consumes **only** the
> Design Resource Repository (26.42) and the Runtime Preview route (26.41). It
> renders no resource HTML, touches no filesystem, and changes no serialization,
> `BuilderState`, `CommandBus`, or the Extension API.
>
> Realises the Library IA of [design-library-architecture.md](design-library-architecture.md) §4.

---

## 1. Audit — existing UI surfaces

| Surface | Role | Reuse decision |
|---|---|---|
| Filament `PageBuilderEditorPage` / `FrontendEditorPage` | The builder editor + its insert flow (patterns/templates → `insert-pattern`). | **Reuse** as the insert consumer (via a decoupled intent event). Not modified. |
| Filament `TemplateLibraryWorkspacePage` (+ Vue bundle) | Editor-side template browse workspace. | **Superseded** conceptually by the Design Library (one library for all kinds). Left intact; no changes. |
| `PatternController` / Insert API | Pattern discovery + apply. | **Reuse** unchanged; the Design Library is additive. |
| Runtime Preview route (26.41) | Sandboxed preview document. | **Reuse** as the sole preview source. |
| Repository (26.42) | Resource resolution. | **Reuse** as the sole data source. |

The Design Library is a **new, additive** admin page; nothing existing is retired or
altered.

---

## 2. Architecture

```
GET /page-builder/library
        │  DesignLibraryController::index  (cms_can guard)
        ▼
DesignLibraryService.viewModel(filters, favorites)
        │  query → DesignResourceRepository (26.42)     ← ONLY data source
        │  previewUrl → route('page-builder.preview')   ← ONLY preview source
        ▼
view('page-builder::design-library.index')  ── server-rendered cards + filters
        │
        └─ <script type=module> design-library.js  (optional enhancement)
             • local favorites (localStorage)
             • preview modal (iframe → Preview route, device switching)
             • insert intent event
```

- **Data:** `DesignLibraryService` builds `LibraryCard` view-models from the
  repository. No business logic — pure composition.
- **Previews:** every preview (card thumbnail + modal) is an `<iframe>` pointing at
  the Runtime Preview route. The Library never renders HTML itself (Rule 2).
- **No filesystem:** cards carry only display metadata + a preview URL; no paths or
  package-internal layout are ever exposed.

---

## 3. Files

| File | Role |
|---|---|
| `src/Library/DesignLibraryService.php` | Query/facets/cards/previewUrl coordinator (repository-only). |
| `src/Library/LibraryCard.php` | Immutable card view-model. |
| `src/Library/FavoritesStoreInterface.php` | Local-favorites seam (no accounts, no sync). |
| `src/Library/SessionFavoritesStore.php` | Default session-scoped favorites. |
| `src/Http/Controllers/DesignLibraryController.php` | `index` (page) + `script` (JS module). |
| `resources/views/design-library/index.blade.php` | Page: filters, grid, empty state, preview modal. |
| `resources/views/design-library/card.blade.php` | One card (lazy preview iframe + metadata + actions). |
| `resources/js/design-library/design-library.js` | Progressive enhancement (ES module, no build step). |
| routes: `page-builder.library`, `page-builder.library.script` | In `routes/admin.php`. |

---

## 4. Features

### 4.1 Browse & filter (server-side)
Filtering uses the repository query API via query parameters (`kind`, `category`,
`vendor`, `tag`, `search`, `favorites`) — it works without JavaScript and is fully
testable. Facet options are derived from the repository's full resource set.

### 4.2 Runtime preview thumbnails
Each available card embeds a **native lazy** (`loading="lazy"`) iframe pointing at
the Preview route, scaled down via CSS. Off-screen thumbnails are not loaded until
near the viewport (performance).

### 4.3 Preview modal with device switching
The **Preview** action opens a modal iframe (Preview route). Device buttons
(Desktop/Tablet/Mobile) swap only the `device` query parameter — the resource data
never changes. Focus is trapped, Escape/backdrop closes, and focus returns to the
trigger.

### 4.4 Local favorites
Favorites are **local only** — the browser stores RIDs in `localStorage` (no
accounts, no sync). `SessionFavoritesStore` is a server-side seam (session-scoped)
for future server/cloud favorites; swapping it is a pure implementation change.

### 4.5 Insert flow (decoupled)
The **Insert** action dispatches a `page-builder:insert-resource` DOM event (and a
same-origin `postMessage`) carrying the resource RID. The existing editor insert
flow consumes this intent — the Library never dispatches a command or touches
serialization/`CommandBus`.

### 4.6 Empty & unavailable states
- No results → an explicit empty state.
- A stale favorite (RID no longer in the repository) → an "unavailable" card
  (`cardForRid` returns an unavailable placeholder), never a hard error.

### 4.7 Accessibility
Semantic HTML (`main`, `role="search"`, `role="list"`, headings per card,
`role="dialog"`/`aria-modal`), labelled controls, `aria-live` result count,
`aria-pressed` favorite toggles, keyboard-operable modal with focus management.

---

## 5. Licensing / entitlement (display only)

Premium/pro resources are **listed and badged** (never hidden), consistent with
[licensing-boundary.md](licensing-boundary.md). Entitlement is a display badge here;
enforcement remains at import/install (out of scope for this UI phase).

---

## 6. What it does NOT do

- No resource HTML rendering (previews only via the Preview route).
- No filesystem/package access; no repository bypass.
- No serialization / `BuilderState` / `CommandBus` / Extension-API change.
- No accounts, no cloud sync (favorites are local; a server seam exists for later).
- No new business logic — pure composition of repository + preview.

---

## 7. Tests

`tests/Feature/DesignLibrary/` (11 tests):
- **Service:** view-model + facets, kind filtering, preview-URL targeting the
  Preview route, favorites marking, unavailable card for a stale RID, sorted facets,
  session-favorites seam.
- **View:** renders cards + filters + preview-modal + insert affordance, exposes no
  filesystem paths, shows the empty state.
- **Controller:** denies without permission (403), serves the JS module with the
  correct content type.

> **Local test note:** move `storage/app/tncms-installed` aside while running plugin
> tests (see [runtime-preview.md §10](runtime-preview.md)); CI runs marker-free.

```bash
php artisan test tests/Feature/DesignLibrary
```

---

*End of design-library-ui.md*
