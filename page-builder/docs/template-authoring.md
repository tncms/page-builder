# Template Authoring (Phase 26.18)

The first authoring capability on top of the Template Runtime (Phase 26.16 contracts,
Phase 26.17 runtime). It turns an existing editor layout into a `TemplateDefinition` and
registers it through the `TemplateProvider` — nothing more.

This is an **authoring foundation**, not a Template Builder. There is no Template Picker,
Management UI, canvas, preview system, persistence, API, or marketplace here.

```
Current Layout → Template Authoring → TemplateDefinition → TemplateProvider
                                                                 │
                                                                 ▼
                                                          Template Runtime
                                                                 │
                                                                 ▼
                                              existing Pattern insertion pipeline
```

## Files

```
resources/editor/templates/authoring/
  templateValidation.ts     root-type gate + metadata validation (pure)
  captureTemplate.ts        wrap selection + build TemplateDefinition (pure)
  templateAuthoringStore.ts Pinia store driving the Save-as-Template flow
  TemplateSaveDialog.vue    metadata form (name / category / description)
  index.ts                  barrel
```

## Capture model

A template is a **complete page-level composition**, so its root rule is stricter than a
Pattern's fragment rule. Two capture scopes:

| Scope       | Source                                   | Wrapping                          |
| ----------- | ---------------------------------------- | --------------------------------- |
| `selection` | the selected **section** or **container** | container → wrapped in a section  |
| `page`      | **all** sections of the current page      | used as-is                        |

**Allowed roots:** section, container, full page.
**Rejected roots:** columns, leaf blocks (text/button/image/…), detached nodes — these are
Pattern-level fragments, not page compositions. `isSupportedTemplateRoot(type, kind)` is the
single gate, shared by the store, dialog, and tests.

Capture reuses the Phase 26.11 serializer verbatim (`serializePattern` → `serializeSection`):

- runtime-only **ids are stripped** at every level (the server re-keys on insert),
- component `data` is **preserved verbatim** — responsive values, token references, style
  bindings, and style states all survive untouched,
- the **source editor state is never mutated** (the serializer deep-clones each node's data).

The result is a `tree`-kind `TemplateDefinition`, directly compatible with
`resolveTemplate()`. No second layout format is introduced.

## Metadata contract

Minimal metadata only:

- `name` — required, ≤ 80 characters (`TEMPLATE_NAME_MAX`).
- `category` — one of `TEMPLATE_LIBRARY_CATEGORIES` (`page`, `landing`, `post`, `archive`,
  `custom`). A plain suggested vocabulary, not a management system.
- `description` — optional; trimmed, omitted when empty.

Explicitly **not** part of the contract: pricing, ownership, marketplace metadata, analytics,
permissions, versioning.

## Save flow

1. `openDialog(scope)` — validates the root (selection) or that the page is non-empty (page)
   and resets the metadata form.
2. The dialog edits `name` / `category` / `description`.
3. `save()` — validates metadata, re-reads the captured layout (it may have changed since the
   dialog opened), serializes it, and builds a **new** `TemplateDefinition` with a fresh
   unique id (via the shared `uniqueId` helper).
4. The definition is registered through `localTemplateProvider.register()`. The provider
   **rejects duplicate ids**, and every save mints a fresh id, so an existing template is
   never overwritten.

Saving is **metadata creation only** — it never dispatches a command, mutates the component
tree, or creates an undo entry. There is no new insertion pipeline; saved templates are
applied later through the existing Template Runtime (`resolveTemplate` / `applyTemplate`),
which itself reuses the Pattern insertion pipeline.

## Relationship to Patterns

Templates depend on Patterns, never the reverse:

- serialization (`serializePattern`), the `PatternTree` shape, and `uniqueId` are all reused
  from `patterns/library`,
- a template's `tree` layout **is** a `PatternTree`,
- applying a template resolves to a tree and replays through the Pattern `insert-pattern`
  command.

Pattern authoring (Phase 26.13) is unchanged and untouched by this phase.

## Future extension points

The architecture stays open for later phases without any speculative fields today:

- **Template Management / Picker UX** — the `TemplateProvider` already exposes
  `all` / `get` / `update` / `remove`; a picker consumes them without touching authoring.
- **Persistent provider** — swap `localTemplateProvider` for a database/theme-backed
  provider behind the same contract.
- **Dynamic slots** — a template is currently a static `PatternTree`. Slots
  (`Template → static PatternTree + dynamic slots`) can be added as an additive layout kind
  later; no slot fields are introduced now.
```
Template
  ├── static PatternTree   (this phase)
  └── dynamic slots        (future — not implemented)
```

## Non-goals

Template Picker, Template Management UI, marketplace, database storage, API, cloud sync,
permissions, version history, import/export, theme templates, dynamic content binding.
