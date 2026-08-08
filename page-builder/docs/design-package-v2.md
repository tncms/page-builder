# Design Package v2 — Format & Layout (Phase 26.40B)

> **Type:** Technical Specification (normative). **No production code.**
> Defines the **`tncms.design-package` v2** container: on-disk/in-transit layout (Step 5),
> asset resolution (Step 7), and dependency resolution (Step 6). The **manifest** fields are
> specified in [design-package-manifest.md](design-package-manifest.md); resources in
> [design-resource-schema.md](design-resource-schema.md); import/export in
> [design-import-export-specification.md](design-import-export-specification.md).
>
> Backward compatibility: v2 is a superset of the Phase 26.26 v1 `tncms.template-package`
> (`version "1.0"`). v1 packages remain importable — see
> [design-version-compatibility.md](design-version-compatibility.md).

**Conventions:** RFC 2119 keywords. Paths use forward slashes. All package-relative paths
are POSIX, case-sensitive, and MUST NOT escape the package root (no `..`, no absolute paths,
no leading `/`).

---

## 1. Package identity

```
schema  = "tncms.design-package"
version = "2.0"
```

A package is a **directory tree** (for authoring / offline) or an **archive** of that tree
(`.zip`, the transport form). The two are equivalent; the manifest and checksums are
computed over the directory tree (see
[design-import-export-specification.md §Export](design-import-export-specification.md)).

---

## 2. Directory layout (Step 5)

```
<package-root>/
├── manifest.json            # REQUIRED — the package manifest (design-package-manifest.md)
├── resources/               # REQUIRED — one file per Design Resource
│   ├── <kind>/<resourceId>.json
│   └── ...                  # e.g. resources/section/hero-split.json
├── assets/                  # OPTIONAL — bundled binary assets (images, media)
│   └── ...
├── fonts/                   # OPTIONAL — font files
│   └── ...
├── icons/                   # OPTIONAL — icon sets / svgs
│   └── ...
├── preview/                 # OPTIONAL — preview media (thumbnails, device shots)
│   └── ...
├── tokens/                  # OPTIONAL — shared token sets referenced by resources/kits
│   └── <tokenSetId>.json
├── locales/                 # OPTIONAL — localization tables
│   └── <locale>.json
├── LICENSE                  # OPTIONAL — human-readable license text
└── CHECKSUMS                # REQUIRED — per-file digests (design-import-export-specification.md)
```

Normative rules:
- `manifest.json` and `CHECKSUMS` MUST be present at the root.
- Every file listed in the manifest MUST exist; every non-manifest, non-`CHECKSUMS` file
  under the root MUST be listed in `CHECKSUMS`.
- `resources/` MUST contain exactly the resources the manifest declares, addressed by
  `<kind>/<resourceId>.json`. The file's contents MUST match the RID derived from its path
  and `source` (source is manifest-level; see §3).
- Directory names (`resources`, `assets`, `fonts`, `icons`, `preview`, `tokens`, `locales`)
  are **invariant KEYS**; producers MUST NOT rename them.
- Unknown top-level files/dirs MUST be rejected on strict import (they are not covered by the
  schema) unless listed under an explicitly reserved `extensions/` dir (reserved, ignored by
  the core importer).

### 2.1 Package `source`

`source` is declared **once** at manifest level (`metadata.source` or derived from the
distribution channel) and applied to every resource in the package. A single package MUST NOT
mix sources. This keeps RIDs (`<source>:<kind>:<resourceId>`) derivable from
`(manifest.source, resource path)`.

---

## 3. Resource files

- One JSON file per resource under `resources/<kind>/<resourceId>.json`, conforming to
  [design-resource-schema.md](design-resource-schema.md).
- Files MUST be **id-free** trees; the importer re-keys on insert.
- The resource file MAY omit `source` (inherited from the manifest) and MAY omit `rid`
  (derived). If present, they MUST be consistent with the manifest/path or import fails.

---

## 4. Asset resolution (Step 7)

Design Resources reference assets by **URL identity only**. Packages MAY *ship* bytes, but
after import every reference in a resource tree is a plain URL. This section defines exactly
how bundled bytes become URLs without violating the boundary.

### 4.1 Reference forms inside resource `data`

An asset reference in a resource tree's `data` is one of:

| Form | Example | Meaning |
|---|---|---|
| Absolute URL | `https://cdn.example/x.webp` | External; used verbatim (after safety check) |
| Root-relative URL | `/media/x.webp` | Host-relative; used verbatim |
| **Package-relative** | `assets/x.webp` | Bundled byte; MUST be rehosted on import (§4.3) |

Package-relative references MUST resolve to a file under the package root and MUST be
declared in the manifest `assets[]`.

### 4.2 Asset safety policy (normative)

- Allowed URL schemes: **http**, **https**, and scheme-less/relative.
- Rejected: `javascript:`, `data:`, `file:`, `vbscript:`, empty. (Restates `isSafeAssetUrl`.)
- All preview URLs additionally pass `sanitizeThumbnail`.
- Any resource containing a rejected scheme is a **blocking** validation error.

### 4.3 Rehosting seam (package-relative → URL)

On import, for each package-relative asset:
1. Verify the file exists, matches its `CHECKSUMS` digest, and matches the declared
   `type`/dimensions in `assets[]`.
2. **Rehost** the bytes into the host's media/asset storage.
3. Obtain a **safe URL** for the stored asset.
4. **Rewrite** every package-relative reference in resource trees to that URL.

Normative guarantees:
- The host storage **id** produced by rehosting is **display-only** and MUST NOT be written
  into any resource tree (consistent with `AssetReference.toMediaResult() → {src, alt?}`).
- Rehosting MUST be **idempotent by content**: importing the same asset twice (same digest)
  MUST reuse the same stored asset/URL (content-addressed), avoiding duplicates.
- If rehosting fails for any asset, the import fails atomically (nothing is registered).

### 4.4 Asset descriptor (manifest `assets[]`)

```ts
interface PackageAsset {
  id: string          // intra-package asset id (referenced by resources if needed)
  path?: string       // package-relative path (bundled) — mutually exclusive with url
  url?: string        // already a URL (external) — mutually exclusive with path
  type: 'image' | 'video' | 'audio' | 'font' | 'icon' | 'other'
  mime?: string
  width?: number      // for image/video
  height?: number
  bytes?: number
  digest?: string     // sha-256 (REQUIRED when path is present; see CHECKSUMS)
}
```

Exactly one of `path` / `url` MUST be present. `digest` MUST be present for `path` assets and
MUST match `CHECKSUMS`.

Fonts, icons, and preview media use the same descriptor shape addressed under their
respective directories; font/icon specifics (families/weights/sets) are in the manifest
(`fonts[]`, `icons[]`) — see [design-package-manifest.md](design-package-manifest.md).

---

## 5. Dependencies (Step 6)

Packages and resources MAY depend on other resources/packages (chiefly kits depending on
shared sections/blocks).

### 5.1 Declaration (manifest `dependencies[]`)

```ts
interface PackageDependency {
  ref: string          // RID (kit member) or packageId
  refType: 'resource' | 'package'
  version?: string     // semver range (versionRange); default "*"
  optional?: boolean   // default false
  reason?: string      // display; why the dependency exists
}
```

- Kit `members` (resource-schema §4) are an *implicit* resource dependency set; explicit
  `dependencies[]` MAY add version pins/ranges and optionality.
- `version` uses the 26.27 `versionRange` grammar (`>=,<=,>,<,=,*`, space-separated
  comparators).

### 5.2 Resolution algorithm (normative)

At import, for each dependency:
1. Locate the referenced resource/package in the **resolved library** (already-installed) or
   **within the same package** (bundled dependency).
2. Check the located version satisfies `version` (range).
3. Classify:
   - **Satisfied** → ok.
   - **Missing & required (`optional=false`)** → blocking `reason`.
   - **Present but version out of range & required** → blocking `reason`.
   - **Missing/out-of-range & optional** → non-blocking `warning`; the dependent resource is
     imported but flagged degraded (e.g. a kit installs available members, records the gap).
4. **Cycle detection:** the dependency graph MUST be acyclic. A cycle is a blocking `reason`.
5. **Depth/fan-out:** transitive dependencies are resolved breadth-first; the importer MUST
   bound total resolution work and MUST NOT install a dependency the manifest did not
   transitively declare (no silent network fetch in the core importer — remote resolution is
   a reserved `remote`/`marketplace` concern).

### 5.3 Determinism

Given the same resolved library state and the same package, dependency resolution MUST be
deterministic (same reasons/warnings, same install set). Resolution MUST NOT depend on
wall-clock, iteration order of hash maps, or randomness (see
[design-ai-compatibility.md](design-ai-compatibility.md) and
[design-import-export-specification.md](design-import-export-specification.md) for the shared
determinism rules).

---

## 6. Relationship to v1

| Aspect | v1 `tncms.template-package` | v2 `tncms.design-package` |
|---|---|---|
| Container | single JSON `{schema,version,metadata,templates[],compatibility?}` | directory/archive tree (§2) |
| Resources | `templates: TemplateDefinition[]` | `resources/<kind>/<id>.json`, kinds block/section/page/kit |
| Assets | inline URLs only | inline URLs **or** bundled + rehosted (§4) |
| Fonts/icons/localization/preview | none | first-class dirs + manifest sections |
| Dependencies | none | `dependencies[]` (§5) |
| Checksums/signature | none | `CHECKSUMS` (required) + signature seam |

A v1 package is accepted by treating its single JSON as an implicit v2 with each
`templates[]` entry mapped to a `page` (or `section`) resource and no assets/deps. See
[design-version-compatibility.md](design-version-compatibility.md) for the migration seam
(`nullMigrationProvider` hook).

---

## 7. Conformance checklist

1. Root has `manifest.json` + `CHECKSUMS`; all files digest-verified (§2).
2. One `source` per package; RIDs derivable from `(source, path)` (§2.1, §3).
3. Resource trees id-free, 2-level, asset-safe (§3, §4.2).
4. Package-relative assets rehosted idempotently to URLs; storage ids never serialized (§4.3).
5. Dependencies resolved deterministically with reasons/warnings + cycle detection (§5).
6. v1 packages import via the migration seam (§6).

---

*End of design-package-v2.md*
