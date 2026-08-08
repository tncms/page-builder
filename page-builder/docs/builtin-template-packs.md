# Page Builder — Built-in Template Packs

*Phase 26.25. Named, versioned, first-party template packs that ship with the CMS. They register
into the Phase 26.24 `BuiltinTemplateDefinitionSource`, then resolve and apply through the
existing runtime — one command, one undo. Validation-first, deterministic, immutable after boot.
**No marketplace, downloads, licensing, remote loading, or new resolver.** A safe,
marketplace-ready foundation.*

```
company-pack (manifest + definitions)  →  validate  →  BuiltinTemplateDefinitionSource (26.24)
        →  TemplateSourceResolver  →  runtime applyTemplate  →  Builder
        └→ (published) builtin Template Library  →  Picker
```

---

## 1. Pack format

A pack is a manifest plus the full `TemplateDefinition`s it ships.

```ts
interface BuiltinTemplatePackManifest {
  id: string          // invariant pack KEY, e.g. 'company-pack'
  name: string
  version: string     // MAJOR.MINOR.PATCH
  description?: string
  schema?: string     // layout schema, must be supported (default 'pagebuilder/01')
  templates: string[] // template ids — must match `definitions` exactly (1:1)
}

interface BuiltinTemplatePack {
  manifest: BuiltinTemplatePackManifest
  definitions: TemplateDefinition[]
}
```

Definitions ship an **id-free inline tree** layout (`kind: 'tree'`); the server re-keys ids on
apply. Packs are authored as typed TS modules (project convention) — see
`templates/builtin/packs/company/`.

### Bundled pack: `company-pack` (v1.0.0)

| Template id | Name | Category |
|---|---|---|
| `hero-basic` | Basic Hero | hero |
| `feature-grid-basic` | Basic Feature Grid | features |
| `cta-basic` | Basic Call To Action | cta |

---

## 2. Validation (validation-first)

`validatePack(pack, { metadata?, knownPackIds? })` runs **before** anything is registered, so a
bad pack fails at load, never at apply. It checks:

- **Manifest** — id / name present, `version` matches `MAJOR.MINOR.PATCH`, `schema` supported,
  pack id not already registered.
- **Reference integrity** — `manifest.templates` ↔ `definitions` are an exact 1:1 (no orphans, no
  missing, no duplicates).
- **Definition** — id / name / layout present; built-in templates must ship an inline tree.
- **Component compatibility** (`collectTreeIssues`) — every section/block has a type; nesting
  obeys the static `canContain` policy (container → column only, column → non-container/column);
  and, when editor metadata is supplied, section/block types are known. Type-existence is
  **permissive** without metadata (mirrors `validateLayout`), so packs validate structurally at
  boot and fully in tests/tools.

Built-in template trees are **id-free**, so — unlike `validateLayout` — id checks are skipped.

---

## 3. Registry (deterministic · duplicate-safe · immutable)

`BuiltinTemplatePackRegistry.register(pack)`:

1. **Idempotent** — a pack id already present is a safe no-op (boot / HMR).
2. **Validate** — any issue throws `BuiltinPackError` (with the issue list). Nothing is
   registered on failure (atomic).
3. **Register** each definition into the `BuiltinTemplateDefinitionSource` (the one built-in store
   — no duplicate storage, no new resolver).

`freeze()` makes the built-in set immutable after boot (new packs then rejected; known packs still
no-op).

---

## 4. Boot & discovery

`registerBuiltinPacks({ freeze })` registers every bundled pack and — by default — publishes each
template's metadata to the Phase 26.21 built-in library (`toLibraryItem(def, 'builtin')`) so it is
**discoverable in the Picker**. It is **not** a top-level side effect: importing the module
registers nothing (deterministic tests). The editor calls it once at boot (resilient — a bad pack
degrades to "no built-in templates", never blocks mounting) and freezes the registry.

---

## 5. Resolution & apply (unchanged runtime)

Once registered, a built-in template resolves through the Phase 26.24 `TemplateSourceResolver`
(`resolveTemplateDefinition('hero-basic', 'builtin')`) and applies through the existing
`applyTemplateFromSource` → `applyTemplate` → `insert-pattern` — **one command, one undo**, exactly
like a user template. Before registration, resolution is an explicit `template_definition_not_found`
(never a fabricated template).

---

## 6. Regression guarantees

Additive only. It does **not** change the runtime, CommandBus, BuilderState, renderer, the Picker
(26.22), the source resolver (26.24), the library (26.21), or Pattern v1. `applyTemplate` and the
`insert-pattern` pipeline are untouched.

Full builder suite green (**1221 tests**). Covered by:
- `TemplatePackValidation.test.ts` — manifest / references / compatibility.
- `TemplateBuiltinPack.test.ts` — registry (idempotent, validation-first, freeze).
- `TemplateBuiltinDefinitionResolution.test.ts` — resolves + publishes to the library.
- `TemplateBuiltinApplyFlow.test.ts` — a built-in template applies end-to-end (one command, one undo).

---

## 7. Marketplace readiness

A future marketplace/remote/cloud source plugs in exactly here: implement its
`TemplateDefinitionSource` (26.24) and register its packs through a registry. The manifest already
carries id / version / schema — the metadata a distribution layer needs. Nothing in this
foundation must be rewritten to add one.
