# Page Builder — Template Runtime

*Phase 26.17. The first runtime layer for the Template subsystem, built on the Phase 26.16
contracts. It establishes the canonical path for resolving and applying a template into a
page — with **no Template UI, persistence, API, slots, or page assignment.***

Templates depend on Patterns (one-way). Applying a template reuses the **existing** pattern
insertion pipeline — no second mutation engine, no direct `BuilderState` writes.

```
TemplateDefinition
        ↓  resolveTemplate()
   PatternTree
        ↓  insertPattern()  →  insert-pattern command
   CommandBus
        ↓
BuilderState / History   (one undo entry, server-selected root)
```

```
TemplateProvider (contract)
        ↓
LocalTemplateProvider (this phase, in-memory)
        ↓
Future Persistent Provider  (swap the `templateProvider` binding)
```

Modules: `resources/editor/templates/provider/` and `resources/editor/templates/runtime/`.

---

## 1. Audit — how this maps onto the real code

Confirmed against the actual codebase (Phase 26.11–26.16):

| Concern | Reality |
|---|---|
| `TemplateDefinition` | `{ id, name, description?, category?, preview?, layout: TemplateLayoutRef, supportedPageTypes?, version? }` |
| `TemplateLayoutRef` | `{ kind:'tree', tree: PatternTree }` \| `{ kind:'patterns', patternIds: string[] }` |
| `TemplateProvider` | `all / get / register / update / remove` (no `duplicate`) |
| `PatternTree` | `{ sections: PatternSectionSnapshot[] }` — id-free, JSON-safe (section → `blocks[]`, container/column → `data.blocks[]`) |
| `insert-pattern` payload | `{ sections }` — id-free; the **server** re-keys ids (`ComponentId::regenerate`) and selects the new root |
| id regeneration | server-side, via the existing command — the client sends id-free sections |
| one history entry | `editor.executeCommand` pushes exactly one undo entry per command; one `insert-pattern` = one entry |

No mismatch required changing the 26.16 contracts. No Pattern v1 file was modified.

---

## 2. Local Template Provider

`LocalTemplateProvider` implements the contract over an in-memory `Map`. It is JSON-safe,
deterministic, isolated in `templates/`, and independent of the pattern registry.

- `register` **rejects a duplicate id** (`duplicate_registration`) — no accidental
  overwrite; use `update` to modify.
- `update` preserves the id and list position.
- Every read/write is **deep-cloned**, so callers can never mutate stored templates.

One active binding is exported — swap it to add a future persistent provider:

```ts
import { templateProvider } from './templates/provider'
```

It is deliberately not a Repository / Manager / Gateway / Bus / Service.

---

## 3. Tree resolution

For `{ kind: 'tree', tree }`, `resolveTemplate` deep-clones the tree and returns it,
preserving hierarchy, component data, responsive values, token references, style bindings,
and style states — **without mutating the template**. An empty (`sections: []`) or invalid
tree fails explicitly (`empty_layout` / `invalid_tree`).

---

## 4. Pattern-composition resolution

For `{ kind: 'patterns', patternIds }`, `resolveTemplate`:

1. Resolves each id through the active `PatternProvider`.
2. **Preserves declared order.**
3. Deep-clones every resolved tree (no live registry references).
4. Merges the section-rooted trees into one `PatternTree`.
5. Fails explicitly on any missing id.

### Atomic failure

Resolution is all-or-nothing:

```
all references resolve  →  merge → apply
        │
     any missing  →  throw missing_pattern  →  NO merge, NO mutation
```

Missing patterns are **never** silently skipped and order is **never** silently changed.

---

## 5. Canonical apply path

`applyTemplate(template, editor, deps?)`:

1. `resolveTemplate(...)` — throws before anything runs on failure.
2. Hands the resolved tree to the existing `insertPattern` (→ `insert-pattern` command).
3. Returns `{ ok, templateId, selected, resolved }`.

Because it reuses `insertPattern`, a template application is **one command = one CommandBus
round-trip = one undo entry**, with server-side id re-keying and existing selection
behaviour. On resolution failure it throws (typed `TemplateRuntimeError`) and **no command
runs, no undo entry is created, and `BuilderState` is untouched**.

`applyTemplateById(id, editor, deps?)` looks the template up via `templateProvider` and
throws `template_not_found` when unknown (no command).

### Apply mode boundary

Only the **insert** mode exists in this phase (insert into the current page). `replace-page`,
`merge-page`, `append-to-region`, and page-type / global assignment are later phases and are
intentionally absent.

---

## 6. Typed errors

`TemplateRuntimeError { code, message, details? }` with codes: `template_not_found`,
`empty_layout`, `missing_pattern`, `invalid_tree`, `unsupported_layout_kind`,
`duplicate_registration`. Errors are explicit and never swallowed.

---

## 7. Future extension points

- **Slots** — not implemented. The runtime does not block a future
  `{ kind: 'slots', … }` layout ref: `resolveTemplate` switches on `layout.kind`, so a new
  kind adds a branch there and a matching guard in the contract. No speculative slot fields
  were added to `TemplateDefinition`.
- **Persistent provider** — implement `TemplateProvider` over a database/theme/API source
  and change the `templateProvider` binding. The runtime and (future) UI are unchanged;
  templates already store JSON-safe data, so no schema migration of patterns/pages.
- **Runtime state store** — not added (no UI consumer yet). A small store for
  resolving/apply status + latest error can layer on later without changing this path.

---

## 8. Guarantees (this phase)

- One local in-memory `TemplateProvider`; one active `templateProvider` binding.
- One canonical `resolveTemplate` and one canonical `applyTemplate`.
- Tree and pattern-composed templates both resolve to **independent** `PatternTree`s.
- Missing pattern references fail explicitly and atomically.
- Apply reuses `insert-pattern` / CommandBus / history — one application = one undo entry;
  no direct `BuilderState` mutation.
- **Pattern v1 unchanged**; no UI, persistence, API, marketplace, slots, or page
  assignment. Full suite green.
