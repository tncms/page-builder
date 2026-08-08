# Page Builder — Unified Insert Experience

*Phase 26.37 (FREE v1). One Insert entry with four categories — **blocks / patterns / templates /
assets** — that ROUTES to the already-built experiences. It is a router + discovery layer only: it
holds no item registry, resolves nothing, dispatches no command, and never touches BuilderState or
the runtime. Undo/redo, selection, and every existing insert pipeline are unchanged.*

```
Insert launcher ─ select(category) ─▶ provider.open() ─▶ existing Picker / flow ─▶ existing runtime
   blocks   ─▶ component palette
   patterns ─▶ Pattern Picker ─▶ insert-pattern command
   templates─▶ Template Picker ─▶ usage → apply
   assets   ─▶ Asset Picker
```

---

## 1. Categories

`INSERT_CATEGORY` — invariant keys `blocks` / `patterns` / `templates` / `assets`, with a canonical
`INSERT_CATEGORY_ORDER` (blocks → patterns → templates → assets). Only labels are localized.

## 2. Provider contract

```ts
interface InsertProvider {
  key: string          // invariant category key
  label: string        // localized
  description?: string
  open(): void         // launch the existing experience — the ONLY capability
}
```

A provider is a thin router entry. `open()` launches an existing Picker/flow; it never resolves
items, inserts a tree, dispatches a command, or writes BuilderState. Providers own no item registry —
blocks/patterns/templates/assets stay in their existing sources.

## 3. Registry

`InsertProviderRegistry` — `register` / `get` / `has` / `all` / `keys`, **deterministic**
(registration order) and **identity-strict** (a duplicate category throws `InsertRegistryError`,
never overwrites).

## 4. Built-in providers + wiring

`registerBuiltinInsertProviders(registry, handlers)` registers the four categories in order, each
with an **injected** `open` handler — so this layer stays decoupled from how each experience opens.
`defaultInsertOpenHandlers()` wires the two categories with a clean store entrypoint
(`patterns → usePatternPickerStore().openPicker()`, `assets → useAssetPickerStore().openPicker()`);
`blocks` (component palette) and `templates` (Template Picker) have no store-level open toggle, so
the host supplies those handlers.

## 5. Panel store + UI

`useInsertPanelStore` is the launcher: `open()` / `close()` / `toggle()` and `sync()` (snapshots the
registry). `select(key)` marks the category active, records `lastOpenedKey`, calls `provider.open()`,
and dismisses the launcher; an unknown/unwired key is a safe no-op. `InsertPanel.vue` renders one
button per category and routes on click.

---

## Boundaries

- **Router only** — no item registry, no resolve, no command, no BuilderState/runtime.
- **Existing pipelines unchanged** — patterns still go through `insert-pattern`, templates through
  usage → apply; undo/redo and selection behave exactly as before.
- Deterministic ordering; duplicate categories rejected.
- Sources unchanged (blocks/patterns/templates/assets); no new source.
- No marketplace, premium, AI generation, bulk/multi-select insertion, drag-drop redesign, or new renderer.

## Future extension points

A new category is one `register(makeInsertProvider(key, open))` call with its label + open handler —
no change to the store, panel, or any existing pipeline.
