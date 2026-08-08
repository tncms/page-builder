# Page Builder — Template Picker Foundation

*Phase 26.22. The first **user-facing** surface over the Phase 26.21 Template Library:
**browse · search · filter · select**. It consumes the `TemplateLibraryProvider` only. It does
**NOT apply, insert, resolve a layout, dispatch a command, or create an undo entry** — applying a
selected template is Phase 26.23. Selection is a metadata handoff, nothing more.*

```
Template Sources → Template Library (26.21) → Template Picker (26.22) → [Apply UX 26.23] → Runtime → Builder
```

---

## 1. Scope

| Built here (26.22) | Deferred |
|---|---|
| Browse metadata (cards) | Apply / insert (26.23) |
| Search (name/description/tags) | Layout resolution |
| Filter by **source** KEY | BuilderState / command / undo |
| Filter by **category** KEY | Marketplace / purchase / licensing |
| **Select** (record identity) | Persistence |

The picker reads **only** `templateLibrary` (the composite `TemplateLibraryProvider`). It never
touches `TemplateProvider`, `LocalTemplateProvider`, the pattern registry, or `BuilderState`.

---

## 2. Data flow

```
templateLibrary.all()  →  useTemplatePickerStore.items  →  results (source→category→search)  →  TemplateCard[]
                                                            └→ select(id) → selection { templateId, source, item }
```

- **`templatePickerFilters.ts`** — pure composition: `filterLibrary(items, {source, category,
  query})` applies source → category → search in order; `libraryCategoryCounts` /
  `librarySourceCounts` drive the chips. No store, no side effects.
- **`useTemplatePickerStore`** (Pinia, `pbTemplatePicker`) — snapshots the library into reactive
  state and exposes `results`, `categories`, `sources`, `selectedItem`, `selection`, plus
  `isEmpty` / `noResults` / `error`. `load()` is fault-isolated: a library failure becomes an
  `error` state, never a thrown exception.
- **`TemplateCard.vue`** — renders one item's metadata. The thumbnail is the item's **URL used
  verbatim** — never resolved from a provider or a media id (Phase 26.20.1). Clicking emits
  `select`.
- **`TemplatePicker.vue`** — search box, source chips, category chips, a card grid, and
  empty / no-results / error states. `onMounted` → `store.load()`.

---

## 3. Selection is a handoff, not an apply

Selecting records identity only:

```ts
interface TemplateSelection {
  templateId: string           // references TemplateDefinition.id within its source
  source: string               // invariant source KEY
  item: TemplateLibraryItem    // metadata (no layout tree)
}
```

`store.selection` is the seam a future **Apply UX (Phase 26.23)** consumes to fetch the full
`TemplateDefinition` from its `TemplateProvider` and apply it through the Template Runtime. The
picker itself performs none of that.

---

## 4. Localization boundary (Architecture Lock #2)

Source and category **KEYS** (`builtin`, `user`, `imported`, `hero`, `landing`, …) are invariant
identifiers used for filtering — never localized. Only their visible chip labels, and UI copy
(placeholders, empty-state messages) would ever be translated. Template names/descriptions are
user content data.

---

## 5. Regression guarantees

Purely additive discovery UI. It does **not** change:

- BuilderState · CommandBus · the renderer · the Template Runtime · the undo stack.
- `TemplateProvider` / `LocalTemplateProvider` (read only, via the library).
- The Template Library (26.21) or Pattern v1.

Full builder suite green (**1178 tests**). Covered by
`templates/picker/__tests__/TemplatePicker.test.ts`.
