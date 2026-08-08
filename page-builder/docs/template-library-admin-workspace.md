# Page Builder — Template Library Admin Workspace

*Phase 26.34 (FREE v1). The admin browse surface over the Phase 26.21 Template Library — the first
of the 26.34–26.39 workspace series. It presents templates as metadata cards with search + filters
and routes "Use template" through the existing usage → apply → runtime flow. **It applies nothing
itself:** no resolve, no `insert-pattern`, no CommandBus, no BuilderState. No marketplace, selling,
catalogs, or premium UI — everything here is free.*

```
TemplateLibraryProvider (26.21) ─▶ Workspace store ─▶ TemplateLibraryWorkspace.vue
                                                          │  "Use template"
                                                          ▼
                              Usage workflow (26.32) ─▶ Apply confirmation (26.23) ─▶ Runtime
```

The workspace is a *presentation + hand-off* layer. Discovery data comes from the library; execution
stays in the runtime. The workspace connects the two and owns neither.

---

## 1. Store

`useTemplateWorkspaceStore` reads **only** `templateLibrary` (the `TemplateLibraryProvider`) and
carries metadata — never layout trees.

- `load()` — fault-isolated snapshot into state (`loading` → `loaded` / `error`).
- `results` — `filterWorkspace` applied (source → category → search).
- State getters: `isEmpty`, `noResults`, plus `categories` / `sources` (key + count) for chips.
- `setQuery` / `setCategory` / `setSource`.
- `use(item)` — hands the item's `TemplateSelection` to the Phase 26.32 usage workflow
  (`useTemplateUsageStore().useSelection`), which opens the existing apply confirmation. The
  workspace applies nothing and touches no BuilderState.

`workspaceFilters.ts` reuses the Phase 26.21 library primitives (`filterLibraryBySource` /
`filterLibraryByCategory` / `searchLibrary`) — pure, no reimplementation.

---

## 2. Components

- **`TemplateLibraryWorkspace.vue`** — the shell: search input, source + category filter chips, a
  results grid, and the loading / empty / no-results / error states. `onMounted → load()`.
- **`WorkspaceTemplateCard.vue`** — a metadata card (thumbnail-or-initial, source + category badges,
  name, description) with a "Use template" button. It emits `use`; it never resolves or applies.

Thumbnails are URL-only (the Phase 26.21 asset boundary); a template with no thumbnail shows an
initial placeholder.

---

## 3. Usage hand-off (26.32 extension)

The workspace browses the **full** library (builtin/local/imported), not just adopted templates, so
it uses the selection-based usage entry added in this phase: `TemplateUsageManager.useSelection` /
`useTemplateUsageStore.useSelection(selection)`. This hands a ready `TemplateSelection` to the
existing apply store's `request` — opening the confirm → apply flow. The id-based `use(id)`
(adoption-scoped) is unchanged.

---

## Boundaries

- **Reads only `TemplateLibraryProvider`** — no TemplateProvider, pattern registry, or BuilderState.
- **Applies nothing** — "Use" hands off through usage → apply; the runtime is the sole executor.
- No `insert-pattern`, no CommandBus, no BuilderState mutation from the UI.
- **No premium UI** — no marketplace, selling, catalogs, pricing, or licensing. FREE v1.
