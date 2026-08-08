# Template Management (Phase 26.19)

Management operations for registered templates, layered on the Template Runtime
(Phase 26.16 contracts, 26.17 runtime) and Template Authoring (Phase 26.18). Search, filter,
rename, duplicate, and delete templates — **through the `TemplateProvider` only**.

This is a **management foundation**, not a Template Builder or Marketplace. There is no
canvas, no preview, no picker, no persistence, and no API.

```
Template Authoring → TemplateProvider ← Template Management (this phase)
                          │
                          ▼
                   Template Runtime → Pattern insertion pipeline
```

## Files

```
resources/editor/templates/management/
  templateActions.ts            rename / duplicate / delete / search / summary (provider-only)
  templateManagementStore.ts    Pinia store: snapshot → search/filter → rename/dup/delete
  TemplateManagementPanel.vue   list + search + category chips + row actions
  TemplateRenameDialog.vue      name form
  TemplateDeleteDialog.vue      delete confirmation
  index.ts                      barrel
```

## Architecture Lock #2 — `category` is a KEY, never localized

`category` is an **invariant identifier** (`page`, `landing`, `post`, `archive`, `custom`),
not a translated label. All logic — the category filter, the `categories` count getter, and
search — runs over stored **keys** and user-entered **names** (data), never over localized
display text. Template names (e.g. "Hero") are user data and are likewise never translated.
No localization subsystem is introduced (the editor has none, and this phase does not add
one).

## Operations — all through the provider

| Operation     | Provider path                                   | Notes                                                        |
| ------------- | ----------------------------------------------- | ------------------------------------------------------------ |
| **Search**    | `all()` snapshot → pure `searchTemplates`       | name/description substring, case-insensitive                 |
| **Filter**    | pure, over the snapshot                         | by category **key**                                          |
| **Rename**    | `update(id, { name })`                          | id + list position preserved                                 |
| **Duplicate** | `get(id)` → fresh id → `register(copy)`         | composed — the provider has no `duplicate`                   |
| **Delete**    | `remove(id)`                                    | applied page content is independent, unaffected              |

### Duplicate without `LocalTemplateProvider` changes

The `TemplateProvider` contract intentionally omits `duplicate` (templates are page
compositions). A copy is therefore **composed from existing operations** rather than by
modifying the provider:

1. `get(id)` — returns a deep clone of the source (the provider clones on read),
2. assign a **fresh unique id** (the shared Pattern `uniqueId` helper) and a `"<name> Copy"`
   name,
3. `register(copy)` — stores it (the provider clones again on write).

The copy shares no references with the original; mutating one never affects the other.

## Store behaviour

- `load()` / `refresh()` re-snapshot `localTemplateProvider.all()` into reactive state, so
  lists update immediately after any mutation.
- Management is **metadata-only**: it never dispatches a command, mutates the component tree,
  or creates an undo entry. Deleting a template does not touch pages already built from it.
- There is no picker or runtime store to sync (those don't exist for templates) — the
  provider is the single source of truth.

## Relationship to Patterns and the runtime

- Reuses `uniqueId` and the `PatternTree` shape from `patterns/library`; Pattern v1 is
  untouched (the pattern registry is never read or written).
- Managed templates still resolve unchanged through `resolveTemplate()` /
  `applyTemplate()` — rename/duplicate change only metadata and identity, not the layout
  contract.

## Non-goals

Template Builder, Template Picker UX, preview/canvas, marketplace, database storage, API,
cloud sync, permissions, version history, import/export, theme templates, localization.
