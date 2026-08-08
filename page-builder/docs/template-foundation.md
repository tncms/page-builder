# Page Builder — Template Foundation (Audit & Contracts)

*Phase 26.16. An architecture-first audit and the minimum contracts for a future Template
subsystem. **No template runtime is built in this phase** — no builder, picker, editor,
storage, API, or UI.*

The goal is to lock the architecture before implementation so the completed **Pattern v1**
subsystem never has to be refactored.

```
Pattern System  →  Template Contracts  →  (future) Template Runtime
```

---

## 1. Architecture audit — domain responsibilities

Four distinct domains, each with one responsibility. They must not be blurred.

| Domain | Responsibility | Scale | Owns |
|---|---|---|---|
| **Pattern** | a **reusable layout fragment** | section / container / column subtree | `PatternDefinition` + `PatternTree` |
| **Template** | a **complete page composition** | a whole page | `TemplateDefinition` (this phase: contract only) |
| **Page** | the **content owner** | one URL/entity | the saved `LayoutPayload` for that page |
| **Theme** | the **rendering layer** | site-wide | Blade/markup that renders the payload |

- A **Pattern** is a fragment you drop into a page (Phase 26.11–26.15). It is authored,
  registered, browsed, managed, and inserted via `insertPattern` → CommandBus.
- A **Template** is a starting point for an *entire page* — a composition of patterns
  and/or an inline page tree. It is page-level, not a fragment.
- A **Page** owns content; a template only *seeds* a page's layout. Applying a template is
  a future runtime concern (it would ultimately produce a `LayoutPayload` for the page
  through the **existing** insert pipeline — no new pipeline).
- A **Theme** renders the resulting payload. Templates describe composition, not rendering;
  the theme remains the rendering layer. (Theme inheritance is explicitly out of scope.)

---

## 2. Dependency map — Templates depend on Patterns

```
        Theme (rendering)
          ▲  renders
          │
        Page  ── owns ──►  LayoutPayload
          ▲
          │ seeded by (future runtime, via existing insert pipeline)
          │
      Template  ──────────────┐
          │ depends on        │ composes
          ▼                   ▼
   PatternTree          Pattern ids ──► PatternProvider ──► PatternRegistry
   (26.11 shape)                         (26.15 seam)

   Patterns DO NOT depend on Templates.  (one-way edge, enforced by contract)
```

The dependency is enforced by the contract itself: `TemplateDefinition.layout` is a
`TemplateLayoutRef` that is **either**

- `{ kind: 'tree', tree: PatternTree }` — an inline page tree reusing the Phase 26.11
  `PatternTree` shape, **or**
- `{ kind: 'patterns', patternIds: string[] }` — a composition of registered patterns,
  which a future runtime resolves through the existing `PatternProvider`.

Both forms reference Pattern constructs. Nothing in the Pattern subsystem imports anything
from `templates/`, so the edge is one-way.

### Reusable components Templates should consume (unchanged)

`PatternDefinition` · `PatternTree` · `PatternProvider` / `LocalPatternProvider` ·
`serializePattern()` · `insertPattern()` · `PatternRegistry` · `CommandBus` ·
`BuilderState`. None are modified or duplicated by this phase.

---

## 3. Contracts

Module: `resources/editor/templates/contracts/` — **types + guards only**.

### TemplateDefinition (metadata only)

```ts
interface TemplateDefinition {
  id: string
  name: string
  description?: string
  category?: string
  preview?: string            // metadata-only preview reference (thumbnail url), never a live render
  layout: TemplateLayoutRef   // 'tree' (inline PatternTree) | 'patterns' (pattern ids)
  supportedPageTypes?: string[]
  version?: string            // a plain label, not a history system
}
```

No persistence, schema migration, version history, or permissions.

### TemplateProvider (design only — not implemented)

```ts
interface TemplateProvider {
  all(): TemplateDefinition[]
  get(id): TemplateDefinition | undefined
  register(template): TemplateDefinition
  update(id, patch): TemplateDefinition | undefined
  remove(id): boolean
}
```

Mirrors the Phase 26.15 `PatternProvider` seam (minus `duplicate`, which is a
fragment-level convenience). **No `LocalTemplateProvider` is shipped** — only the contract.
A test-only stub proves the interface is consistent and implementable.

---

## 4. Extension points

- **Future runtime**: a `templates/runtime/` module would add a `LocalTemplateProvider`
  (Map-backed, mirroring `LocalPatternProvider`), a registry, and an "apply template"
  action that resolves the layout ref and seeds a page's `LayoutPayload` through the
  **existing** `insert-pattern` command — one undo entry, no new pipeline.
- **Provider swap**: like patterns, the UI would consume a `templateProvider` binding, so a
  database/theme/plugin provider can replace the local one without UI edits.
- **Layout resolution**: `isTemplateTreeLayout` / `isTemplatePatternsLayout` are the seam a
  future resolver switches on — inline trees insert directly; pattern compositions resolve
  each id via `PatternProvider` then compose.

---

## 5. Migration strategy — local → persistent providers

The seam is designed so persistence is additive:

1. **Today (this phase)**: contracts only. No storage.
2. **Local runtime (future)**: `LocalTemplateProvider` holds templates in memory (reset on
   reload), exactly like the pattern registry. UI depends on the `TemplateProvider`
   contract, never the concrete store.
3. **Persistent provider (future)**: implement `TemplateProvider` over a database/theme/API
   source and swap the exported binding. Because the UI and the layout contract are
   unchanged, **no UI or contract refactor is required** — only the provider changes.
4. **Serialization**: templates already store JSON-safe data (a `PatternTree` or id list),
   so a persistent provider serializes `all()` with no schema migration of existing
   patterns or pages.

---

## 6. Guarantees (this phase)

- **No runtime**: no builder, picker, editor, resolver, registry, or provider
  implementation — contracts and guards only.
- **No storage/API/persistence/migration**, no theme inheritance, marketplace, cloud,
  import/export, version history, or permissions.
- **Pattern v1 unchanged**: nothing in `patterns/` was modified; contract tests assert the
  `PatternProvider` surface and `createPatternDefinition` output are intact.
- **One-way dependency**: `templates/` imports from `patterns/`; `patterns/` never imports
  from `templates/`.
