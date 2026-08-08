# Page Builder — Template Starter Packs & Distribution

*Phase 26.28. Named, versioned **groups** of templates a site can adopt. Built-in / local pack
sources with a validation-first, identity-strict registry that reuses the Phase 26.27 compatibility
system. A pack **references** templates by id — it never owns or stores them, so removing a pack
never deletes a template. This does **not** replace the Template Library, the runtime, the package
format, or the Picker. No marketplace / purchase / payment / licensing.*

```
Starter Packs (metadata, reference template ids)  →  registry (validate + compatibility)  →  discovery
Templates continue to live in their own sources (built-in / user / imported) and apply unchanged.
```

---

## 1. Contract

```ts
interface TemplateStarterPack {
  id: string
  name: string
  version: string      // X.Y.Z
  description?: string
  category: string     // invariant KEY
  templates: string[]  // template ids (references — the pack does not own them)
  thumbnail?: string   // a URL, never a media/attachment id
  source?: string      // 'builtin' | 'local' | 'imported' (default 'builtin')
  compatibility?: PackageCompatibility  // reused from Phase 26.27
}
```

**Identity rules:** `id`, `category`, and `source` are invariant KEYS (never localized). A pack
groups templates by reference; the templates themselves keep using `TemplateDefinition` and resolve
/ apply through the unchanged runtime.

### Pack sources

`builtin` · `local` · `imported` are active KEYS; `marketplace` / `remote` / `cloud` are reserved
for the future (no provider here).

---

## 2. Validation (validation-first)

`validateStarterPack(pack, { knownPackIds?, knownTemplateIds?, environment? })` runs before
registration:

- **Metadata** — id / name / `X.Y.Z` version / category present; thumbnail (if any) a safe URL;
  pack id not already registered.
- **References** — non-empty, no duplicate ids; and — when `knownTemplateIds` is supplied — every
  referenced template must exist (`missing-template`). Without a known set, reference checking is
  **permissive**, keeping the registry decoupled from where templates live.
- **Compatibility** — reuses the Phase 26.27 `checkCompatibilityRequirements` (builder range +
  required components/features). **There is one compatibility system**, not a second.

---

## 3. Registry (deterministic · identity-strict · immutable)

`TemplateStarterPackRegistry`:

- `register(pack, opts)` — validates first; **duplicate ids are rejected** (`StarterPackError`);
  an invalid or incompatible pack throws before anything is stored. Returns non-blocking
  compatibility `warnings`.
- `find` / `all` return **immutable clones** (stored metadata can't be mutated from outside).
- `remove(id)` drops a pack; **referenced templates are untouched** (the pack owns none).
- `freeze()` makes the set immutable after boot.

`registerStarterPacks({ registry?, knownTemplateIds?, environment?, freeze? })` registers the
bundled packs deterministically and **idempotently** (by pack id). It is not a top-level side
effect. The editor calls it at boot after the built-in packs (so referenced templates exist),
resiliently.

### Bundled packs

| Pack id | Category | Templates |
|---|---|---|
| `company-starter-pack` | business | hero-basic · feature-grid-basic · cta-basic |
| `agency-starter-pack` | agency | hero-basic · feature-grid-basic |

Both reference the real Phase 26.25 built-in template ids.

---

## 4. Boundaries

- **Not a Library replacement** — the registry surfaces pack metadata for future discovery; the
  Template Library (26.21) still owns template discovery. Publishing pack metadata into the library
  is a future seam.
- **Not a package** — a starter pack references ids; the Phase 26.26 `TemplatePackage` (which
  carries full definitions for transport) is a separate concept and is unchanged.
- **Incompatible/invalid packs are rejected**, never partially registered.

---

## 5. Regression guarantees

Additive only. It does **not** change the runtime, apply/resolve, CommandBus, BuilderState, the
renderer, the Picker (26.22), the Apply UX (26.23), the source resolver (26.24), built-in packs
(26.25), or the package format (26.26). The Phase 26.27 compatibility change is a **behavior-
preserving extraction** (`checkCompatibilityRequirements`) — `checkPackageCompatibility` is
unchanged and its tests still pass.

Full builder suite green (**1256 tests**). Covered by
`templates/starterPacks/__tests__/TemplateStarterPack.test.ts`.

---

## 6. Distribution readiness

Starter packs are the built-in/local distribution primitive. A future marketplace/remote/cloud
layer adds a pack source and (optionally) publishes pack metadata to the library — with no change to
the runtime, the package format, or the Picker.
