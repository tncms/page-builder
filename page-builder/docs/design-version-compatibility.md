# Design Version & Compatibility (Phase 26.40B)

> **Type:** Technical Specification (normative). **No production code.**
> Defines the versioning scheme and the **compatibility matrix** (Step 13) across builder,
> package format, resource payload schema, theme/tokens, and marketplace metadata. Reuses
> Phase 26.27 `PackageCompatibility` + `versionRange` and the `nullMigrationProvider` seam.
>
> Related: [design-resource-schema.md §7](design-resource-schema.md),
> [design-package-manifest.md §9](design-package-manifest.md),
> [design-import-export-specification.md §A.5/A.7](design-import-export-specification.md),
> [design-token-specification.md §8](design-token-specification.md),
> [page-builder-versioning-and-deprecation-policy.md](page-builder-versioning-and-deprecation-policy.md).
>
> **Conventions:** RFC 2119 keywords. All versions are semver `X.Y.Z` unless noted.

---

## 1. Versioned axes

| Axis | Identifier | Current | Meaning |
|---|---|---|---|
| **Builder** | `BUILDER_VERSION` | `1.0.0` | The page-builder runtime/API version |
| **Package format** | `schema` + `version` | `tncms.design-package` / `2.0` | The container format |
| **Package format (legacy)** | | `tncms.template-package` / `1.0` | v1, still importable |
| **Resource payload schema** | `schemaVersion` | `"1"` | The id-free tree schema (`SUPPORTED_TEMPLATE_SCHEMA_VERSIONS = ["1"]`) |
| **Resource content** | `version` | per resource | The design's own semantic version |
| **Package content** | `metadata.contentVersion` | per pack | The pack's own semantic version |
| **Theme/tokens** | theme-provided | per theme | Token availability (design-token-spec) |
| **Marketplace metadata** | `marketplace.schemaVersion` | per channel | Commercial metadata schema (design-marketplace-metadata) |

These axes version **independently**. A single import evaluates compatibility across all of
them.

---

## 2. Semver rules (normative)

- Format: `MAJOR.MINOR.PATCH`, parsed by the existing `versionRange` util.
- **MAJOR** bump = breaking change (incompatible); **MINOR** = additive/backward-compatible;
  **PATCH** = fixes, no contract change.
- **Ranges** use the 26.27 grammar: `>=`, `<=`, `>`, `<`, `=`, `*`, space-separated
  comparators (e.g. `>=1.0 <2.0`, `*`). `satisfiesRange(version, range)` is the authority.
- Partial versions (`X`, `X.Y`) parse via `parseVersion` (treated as lower bounds where a
  full version is expected); resources SHOULD emit full `X.Y.Z`.
- A resource/pack with **no** builder range is **unconstrained** (compatible), matching v1.

---

## 3. Compatibility evaluation model

Reuses `checkPackageCompatibility(pkg, environment)` → `{ compatible, reasons[], warnings[] }`:

```ts
interface BuilderEnvironment {
  builderVersion: string                  // e.g. "1.0.0"
  supportedPackageSchemas?: string[]      // e.g. ["tncms.design-package","tncms.template-package"]
  supportedPackageVersions?: string[]     // e.g. ["2.0","1.0"]
  supportedSchemaVersions?: string[]      // e.g. ["1"]
  components?: Set<string>                // registered component type KEYS (enables missing-component detection)
  features?: Set<string>                  // registered feature KEYS
  tokens?: Set<string>                    // resolvable token KEYS (enables missing-token WARNINGS)
}
```

Decision rules:
- **Package schema/version** not in `supported*` → blocking `reason`
  (`version.unsupported_schema` / `version.unsupported_version`) — unless a migrator applies (§6).
- **Payload `schemaVersion`** not in `supportedSchemaVersions` → blocking `reason` unless
  migratable.
- **`builder` range** not satisfied by `builderVersion` → blocking `reason`
  (`version.builder_out_of_range`).
- **`requiredComponents`/`requiredFeatures`:** each missing **known** → blocking `reason`;
  each **unverifiable** (no known set to check against) → non-blocking `warning`.
- **Tokens:** referenced token KEYS not resolvable in the theme → non-blocking `warning`
  (resource still renders via fallbacks) — never blocking.

---

## 4. Compatibility matrix (Step 13)

Legend: ✅ compatible · ⚠️ warning (installs, degraded/flagged) · ⛔ blocking reason ·
🔁 migratable (see §6).

| Producer ↓ / Consumer builder → | builder in `builder` range | builder **> range MAJOR** | builder **< range** |
|---|---|---|---|
| Package `version` in `supportedPackageVersions` | ✅ | ⛔ `builder_out_of_range` | ⛔ |
| Package `version` **older** (v1 → v2 consumer) | 🔁 migrate → ✅ | 🔁/⛔ | ⛔ |
| Package `version` **newer** than consumer supports | ⛔ `unsupported_version` | ⛔ | ⛔ |
| Payload `schemaVersion` supported | ✅ | — | — |
| Payload `schemaVersion` older | 🔁 migrate → ✅ | 🔁 | 🔁 |
| Payload `schemaVersion` newer | ⛔ `unsupported_schema` | ⛔ | ⛔ |
| `requiredComponents` all present | ✅ | | |
| `requiredComponents` missing (known) | ⛔ `missing_component` | | |
| `requiredComponents` unverifiable | ⚠️ | | |
| `requiredFeatures` missing (known) | ⛔ `missing_feature` | | |
| Token refs unresolved | ⚠️ (fallback renders) | | |
| Marketplace metadata newer schema | ⚠️ (ignore unknown fields) | | |

General principle: **runtime-affecting** mismatches (component/feature/schema) block; **degradable**
mismatches (tokens, unknown metadata, optional deps) warn.

---

## 5. Forward/backward compatibility policy

- **Backward (newer consumer, older producer):** MUST be supported via migration (§6). v1
  packages MUST import on a v2-capable builder.
- **Forward (older consumer, newer producer):** NOT guaranteed. A newer package
  version/schema than the consumer supports is a blocking `reason`; consumers MUST fail
  cleanly with an actionable message (upgrade the builder), never partially import.
- **Unknown additive fields:** consumers MUST ignore unknown **optional** metadata fields
  (tolerant reader) rather than reject — this lets MINOR additions roll out safely. Unknown
  **required** structure is a blocking `reason`.

---

## 6. Migration (seam) & the `nullMigrationProvider`

```ts
interface TemplateMigrationProvider {
  canMigrate(fromSchemaVersion: string, toSchemaVersion: string): boolean
  migrate(pkg: DesignPackage): DesignPackage
}
```

- Today `nullMigrationProvider` is registered (contract-only). It is the hook where real
  migrators attach (v1→v2, schema `"1"`→future).
- **v1 → v2** is defined behavior even with the null provider: a `tncms.template-package` is
  read by mapping each `templates[]` entry to a `page` (or `section`) resource, no
  assets/deps (design-package-v2 §6). This mapping is deterministic.
- Migrations MUST be **pure and deterministic**: same input package → same migrated package.
- A migration path that does not exist for an older schema → blocking `reason`
  (`version.no_migration_path`).

---

## 7. Identity, rename & deprecation

- A resource's **RID is immutable**. Changing `source`/`kind`/`resourceId` yields a **new**
  resource, not a new version (resource-spec §3.2).
- To evolve a resource, bump its `version` (semver). To *replace* one, publish a new RID and
  mark the old one **deprecated** via marketplace metadata (a display flag), optionally with a
  `supersededBy: RID` pointer. Deprecation MUST NOT break already-installed content
  (grandfathering — licensing-boundary §4.1).
- Token/component/category **renames** are builder-version events, handled by:
  - tokens → fallback still renders + a `warning` (design-token-spec §8);
  - components → `requiredComponents` gate (⛔ if the new KEY is required and absent);
  - categories → tolerant reader (unknown category shown under its raw KEY).
- Deprecation windows and SemVer obligations follow
  [page-builder-versioning-and-deprecation-policy.md](page-builder-versioning-and-deprecation-policy.md).

---

## 8. Determinism

Compatibility evaluation and migration MUST be deterministic and side-effect-free (no
network, no clock). Given a fixed `BuilderEnvironment` snapshot and package, the
`{compatible, reasons, warnings}` result is reproducible — a prerequisite for the import
guarantees in [design-import-export-specification.md](design-import-export-specification.md).

---

## 9. Conformance checklist

1. All version axes tracked; semver + `versionRange` used consistently (§1–§2).
2. Compatibility yields deterministic blocking `reasons` vs non-blocking `warnings` (§3, §8).
3. Matrix respected: runtime-affecting → block, degradable → warn (§4).
4. Backward compat via deterministic migration; forward incompat fails cleanly; tolerant
   reader for unknown optional fields (§5–§6).
5. RIDs immutable; rename = new resource; deprecation grandfathers installed content (§7).

---

*End of design-version-compatibility.md*
