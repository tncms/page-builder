# Page Builder — Template Versioning & Compatibility

*Phase 26.27. A **metadata** layer that lets the import boundary reason about whether a package
fits this builder. It is validation only — it **never changes how a template resolves or applies**,
and it never migrates or mutates a package. A pure checker gates import: compatible → accepted,
incompatible → rejected with reasons, warnings preserved. Migration is a contract **seam** only. No
marketplace.*

```
Package (+ optional compatibility)  →  checkPackageCompatibility  →  import gate  →  Imported source (26.24)
                                          (reasons block · warnings inform)
```

---

## 1. Contracts

### Version metadata (per template/package)

```ts
interface TemplateCompatibility {
  schemaVersion: string    // compatibility-schema KEY, e.g. '1'
  templateVersion: string  // X.Y.Z
  builderVersion?: string  // optional target version/range
}
```

`validateTemplateCompatibility(meta)` returns issues: unknown `schemaVersion`, non-`X.Y.Z`
`templateVersion`, or an invalid optional `builderVersion`. Empty = valid.

### Package compatibility (additive, optional)

`TemplatePackage` gains one optional field (existing packages are unaffected — no field means
"unconstrained"):

```ts
interface PackageCompatibility {
  builder?: string             // semver range, e.g. '>=1.0'
  requiredComponents?: string[] // capability KEYS
  requiredFeatures?: string[]   // capability KEYS
}
```

### Environment

```ts
interface BuilderEnvironment {
  builderVersion: string                 // current builder (default '1.0.0')
  supportedPackageSchemas?: readonly string[]
  components?: ReadonlySet<string>       // known component types (enables missing detection)
  features?: ReadonlySet<string>         // known features
}
```

---

## 2. The check (pure, detect-only)

`checkPackageCompatibility(pkg, environment?)` → `{ compatible, reasons, warnings }`:

- **Schema** — the package schema must be in the environment's supported set, else
  `unsupported-schema` (blocking).
- **Builder** — if `compatibility.builder` is declared, the current builder version must satisfy the
  range, else `builder-incompatible` (blocking). An unparseable range is `invalid-builder-range`.
- **Required components / features** — declared but **missing from a known set** →
  `missing-component` / `missing-feature` (blocking). Declared but the environment lists **no** known
  set → `*-unverified` **warning** (non-blocking — the check never blocks what it cannot verify).

`compatible` is true iff `reasons` is empty. `warnings` never block. The checker only **detects**
incompatibility and missing capabilities — it never rewrites the package.

A minimal version-range util (`versionRange.ts`) backs this: exact versions, `>=`/`>`/`<=`/`<`/`=`
comparators, space-separated AND ranges, and `*`. Not full semver (no `^`/`~`/`||`/pre-release) —
deliberately small.

---

## 3. Import gate

`importTemplatePackage` runs the check **after schema/content validation and before any
registration**:

- **Compatible** → import proceeds exactly as in Phase 26.26; `result.warnings` carries any
  non-blocking notes.
- **Incompatible** → throws `TemplatePackageError` with the reasons; **nothing is registered**
  (atomic, as before).

A package with no `compatibility` block passes the gate unchanged, so **existing packages import
identically**.

---

## 4. Migration seam (contract only)

```ts
interface TemplateMigrationProvider {
  canMigrate(fromSchemaVersion: string, toSchemaVersion: string): boolean
  migrate(pkg: TemplatePackage): TemplatePackage  // must never mutate its input
}
```

**No migrations are implemented.** The bundled `nullMigrationProvider` reports `canMigrate → false`
and throws on `migrate`. Incompatible packages are **rejected**, never silently migrated or
partially accepted.

---

## 5. Regression guarantees

Additive only. It does **not** change the runtime, apply/resolve, CommandBus, BuilderState, the
renderer, the Picker (26.22), the Apply UX (26.23), the source resolver (26.24), built-in packs
(26.25), or the package export/format (26.26 — the `compatibility` field is optional). The import
gate is backward compatible: packages without compatibility metadata behave exactly as before.

Full builder suite green (**1245 tests**). Covered by
`templates/compatibility/__tests__/TemplateCompatibility.test.ts`.

---

## 6. Marketplace readiness

Version and compatibility metadata are the last primitives a distribution layer needs: a future
marketplace can gate installs on `builder` range and required capabilities using this exact checker,
and a real `TemplateMigrationProvider` can slot into the seam — with no change to the runtime,
import, or package format.
