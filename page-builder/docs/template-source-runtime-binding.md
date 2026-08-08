# Page Builder — Template Source Runtime Binding

*Phase 26.24. The missing link between **Library identity** and the **runtime**. The Library
(26.21) is metadata-only — a `TemplateLibraryItem` has no layout tree. To apply a template the
runtime needs the full `TemplateDefinition`. This layer **resolves a definition by (templateId,
source)** and feeds it to the existing, unchanged runtime. Built-in templates now apply
end-to-end. No marketplace / packages / licensing / payment / analytics.*

```
Library item (source:templateId)  →  Source Resolver  →  TemplateDefinition  →  runtime applyTemplate  →  Builder
```

---

## 1. The gap this closes

Before 26.24, `applyTemplateById` resolved only through the user `TemplateProvider`, so a
**built-in** or **imported** selection had no definition and always failed. This phase adds a
definition-resolution layer keyed by source, without duplicating storage or changing the runtime.

---

## 2. Contracts

```ts
// One source's definitions.
interface TemplateDefinitionSource {
  readonly source: string
  getDefinition(templateId: string): TemplateDefinition | null
}

// Resolve across sources by dispatching on the source KEY.
interface TemplateDefinitionProvider {
  getDefinition(templateId: string, source: string): TemplateDefinition | null
}
```

A miss is an explicit `null` — never a fabricated template, never a silent fallback to another
source.

---

## 3. Sources

| Source | Store | Notes |
|---|---|---|
| `builtin` | `BuiltinTemplateDefinitionSource` (in-memory) | Seeded via `register`, empty by default. No downloads. |
| `user` | `UserTemplateDefinitionSource` | **Delegates** to the runtime `TemplateProvider` — single source of truth, no copy. |
| `imported` | `ImportedTemplateDefinitionSource` (in-memory) | Future import/export seam; empty → explicit not-found. |

Built-in and imported share `StaticTemplateDefinitionSource` (in-memory, immutable clones on read,
duplicate-id rejected). This mirrors the 26.21 library's Static/Builtin/Imported shape.

---

## 4. Resolver

`TemplateSourceResolver` implements `TemplateDefinitionProvider`. It selects the source whose KEY
matches and asks it for the definition:

- **Unknown source KEY** → throws `unsupported_template_source` (explicit, never "null-as-unknown").
- **Known source, no such template** → returns `null`.

The default `templateSourceResolver` composes builtin + user + imported. Add a future source
(marketplace/remote/cloud) by extending this one composition — nothing downstream changes.

---

## 5. Runtime binding (apply pipeline unchanged)

`applyTemplateFromSource(templateId, source, editor, deps)`:

1. `resolveTemplateDefinition` → the full definition (throws `unsupported_template_source` /
   `template_definition_not_found` **before any command**).
2. Hands it to the **existing, unchanged** runtime `applyTemplate`.

So the apply chain is byte-for-byte the same as before — `applyTemplate` → `resolveTemplate` →
`insert-pattern` command → Builder — **one command, one undo**. `applyTemplate`,
`applyTemplateById`, `resolveTemplate`, `insertPattern`, the CommandBus, and BuilderState are all
untouched. Resolution throwing before the command keeps every failure **atomic** (no command, no
undo, no mutation).

The Phase 26.23 Apply UX intent (`applyTemplateSelection`) now routes through this binding, so a
Picker selection of any source resolves and applies through one path. The Picker (26.22) and the
Apply UX store/dialog/container are otherwise unchanged.

---

## 6. Errors (typed, explicit)

Added to `TemplateRuntimeErrorCode`:

- `template_definition_not_found` — a known source has no such template.
- `unsupported_template_source` — the source KEY is not registered.

---

## 7. Regression guarantees

Additive binding only. It does **not** change the runtime apply chain, the CommandBus, the
renderer, the Picker (26.22), the Library (26.21), or Pattern v1. `applyTemplateById` still
resolves via its `TemplateProvider` and still throws `template_not_found` for direct use.

Full builder suite green (**1201 tests**). Covered by
`templates/source/__tests__/TemplateSourceBinding.test.ts`.

---

## 8. Result

A **built-in** template (e.g. `builtin:agency-home`) seeded into
`builtinTemplateDefinitionSource` now applies end-to-end — one command, one undo — through the
same runtime path as a user template. Built-in template packs, and later imported/marketplace
sources, plug in here with no rewrite.
