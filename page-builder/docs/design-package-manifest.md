# Design Package Manifest (Phase 26.40B)

> **Type:** Technical Specification (normative, field-level). **No production code.**
> Specifies `manifest.json` for a `tncms.design-package` v2 (Step 4). Container layout is in
> [design-package-v2.md](design-package-v2.md); resources in
> [design-resource-schema.md](design-resource-schema.md); compatibility in
> [design-version-compatibility.md](design-version-compatibility.md); marketplace/licensing
> metadata in [design-marketplace-metadata.md](design-marketplace-metadata.md).
>
> **Conventions:** RFC 2119 keywords. JSON wire format. Invariant KEYS never localized.

---

## 1. Top-level shape

```ts
interface DesignPackageManifest {
  schema: 'tncms.design-package'   // fixed
  version: '2.0'                   // package format version (not the pack's content version)

  metadata: PackageMetadata        // §2
  resources: ResourceEntry[]       // §3 — index of resources/<kind>/<id>.json
  assets?: PackageAsset[]          // design-package-v2 §4.4
  fonts?: FontEntry[]              // §4
  icons?: IconEntry[]              // §5
  tokens?: TokenSetEntry[]         // §6 (see design-token-specification.md)
  localization?: LocaleEntry[]     // §7
  previewAssets?: PreviewAsset[]   // §8 (see design-preview-specification.md)
  compatibility?: PackageCompatibility  // §9
  dependencies?: PackageDependency[]    // design-package-v2 §5
  license?: LicenseMetadata        // §10
  marketplace?: MarketplaceMetadata     // §11 (see design-marketplace-metadata.md)
  signature?: SignatureBlock       // §12 (seam)
  checksums: ChecksumIndex         // §13 (mirrors CHECKSUMS file)
}
```

---

## 2. `metadata` (REQUIRED)

```ts
interface PackageMetadata {
  id: string          // invariant KEY, globally unique package id (reverse-dns or slug)
  name: string        // display; localizable
  description?: string // display; localizable
  contentVersion: string  // semver X.Y.Z — version of THIS pack's content
  source: string      // invariant source KEY applied to all resources (design-package-v2 §2.1)
  author?: ResourceAuthor
  vendor?: ResourceVendor
  tags?: string[]     // invariant KEYS
  createdAt?: string  // ISO-8601 UTC; provided by exporter input, NOT generated at runtime (determinism)
}
```

| Field | Req | Validation |
|---|---|---|
| `id` | R | `^[a-z0-9]+([._-][a-z0-9]+)*$`, len ≤ 128; globally unique |
| `name` | R | 1–120 chars |
| `contentVersion` | R | semver `^\d+\.\d+\.\d+$` |
| `source` | R | ∈ source KEY set (resource-spec §3.3); one per package |
| `createdAt` | O | ISO-8601; supplied as export input (never `now()` during a deterministic build) |

> `version` (format) vs `metadata.contentVersion` (content) are distinct and both REQUIRED.

---

## 3. `resources[]` (REQUIRED)

An index of the resource files; the file bodies are authoritative for payload.

```ts
interface ResourceEntry {
  kind: 'block' | 'section' | 'page' | 'kit'
  resourceId: string          // slug; file at resources/<kind>/<resourceId>.json
  version: string             // semver; MUST match the resource file
  category: string
  name: string                // display (mirror of resource; localizable)
  entitlement?: 'free' | 'pro' | 'premium'  // classification only
  digest: string              // sha-256 of the resource file (matches CHECKSUMS)
}
```

Rules:
- One entry per resource file; the set MUST exactly match the files under `resources/`.
- `digest` MUST match the resource file's `CHECKSUMS` digest.
- `(kind, resourceId)` MUST be unique within the package.

---

## 4. `fonts[]`

```ts
interface FontEntry {
  family: string          // e.g. "Inter"
  weights?: number[]      // e.g. [400,600,700]
  styles?: ('normal'|'italic')[]
  src?: string            // package-relative path under fonts/ OR safe URL
  display?: 'auto'|'block'|'swap'|'fallback'|'optional'  // default "swap"
  subset?: string[]       // optional unicode-range/subset hints
  digest?: string         // REQUIRED when src is package-relative
}
```
- Bundled font files are rehosted to URLs like any asset (design-package-v2 §4.3).
- `display` defaults to `swap` (performance).

---

## 5. `icons[]`

```ts
interface IconEntry {
  set: string             // invariant icon-set KEY, e.g. "acme-icons"
  src?: string            // package-relative svg/sprite under icons/ OR safe URL
  names?: string[]        // optional enumerated icon KEYS provided by the set
  digest?: string         // REQUIRED when src is package-relative
}
```
- Resources reference icons by `set` + name KEY; unknown icon → render fallback, never reject.

---

## 6. `tokens[]` (token sets)

```ts
interface TokenSetEntry {
  id: string              // invariant token-set KEY
  path: string            // package-relative under tokens/<id>.json
  digest: string
}
```
Token grammar and resolution are in
[design-token-specification.md](design-token-specification.md). The manifest only indexes
token sets; resources bind individual tokens via `tokens` (resource-schema §6).

---

## 7. `localization[]`

```ts
interface LocaleEntry {
  locale: string          // CMS locale code (lowercase)
  path: string            // package-relative under locales/<locale>.json
  digest: string
}
```
- Locale files hold display-string tables (resource-schema §9). KEYS are never localized.
- A missing locale falls back to base strings; resolution is deterministic (no network).

---

## 8. `previewAssets[]`

Indexed here, specified in [design-preview-specification.md](design-preview-specification.md).
URL-only, sanitized; bundled preview media rehosted like other assets.

---

## 9. `compatibility` (package-level)

```ts
interface PackageCompatibility {
  schemaVersion: string           // payload/tree schema, e.g. "1"
  builder?: string                // semver range (versionRange)
  requiredComponents?: string[]   // component type KEYS
  requiredFeatures?: string[]     // feature KEYS
}
```
- Reuses Phase 26.27. Package-level compatibility is the union floor; individual resources
  MAY declare stricter C7. Evaluation → blocking `reasons` vs non-blocking `warnings`.
- See [design-version-compatibility.md](design-version-compatibility.md).

---

## 10. `license` (LicenseMetadata)

```ts
interface LicenseMetadata {
  id: string              // license KEY (SPDX-ish, e.g. "MIT", "commercial", "cc-by-4.0")
  name?: string           // display
  url?: string            // safe URL to full text
  file?: string           // package-relative path (usually "LICENSE")
  redistributable?: boolean
}
```
- Descriptive/legal metadata. It MUST NOT gate runtime; entitlement enforcement is separate
  (see [licensing-boundary.md](licensing-boundary.md)).

---

## 11. `marketplace` (optional)

Commercial/marketplace fields (ratings, downloads, pricing, trial, verification). Specified
in [design-marketplace-metadata.md](design-marketplace-metadata.md). MUST NOT affect runtime,
payload, or resource equality.

---

## 12. `signature` (SignatureBlock) — seam

```ts
interface SignatureBlock {
  algorithm: string       // e.g. "ed25519"
  keyId: string           // signer key id
  value: string           // base64 detached signature over the canonical manifest+CHECKSUMS
  signedAt?: string       // ISO-8601 (input, not runtime-generated)
}
```
- Signing/verification is a **seam** (contract-only this phase; mirrors `nullMigrationProvider`).
- Trust policy by source (community advisory, premium required-configurable) is in
  [design-package-v2 §6 / marketplace spec]. Verification results feed the same
  reasons/warnings channel.

---

## 13. `checksums` (ChecksumIndex) & the `CHECKSUMS` file

```ts
interface ChecksumIndex {
  algorithm: 'sha-256'    // fixed for v2
  files: { path: string; digest: string; bytes: number }[]  // sorted by path
}
```
- `manifest.checksums.files` MUST mirror the root `CHECKSUMS` file exactly.
- Every packaged file except `CHECKSUMS` itself MUST appear once, addressed by
  package-relative path, sorted ascending by path (determinism).
- On import, each file's digest MUST match or the import fails atomically.
- The signature (if present) covers the canonical manifest **with** the checksum index, so a
  single verification protects all bytes.

---

## 14. Determinism (manifest)

The manifest MUST be **canonically serialized** for reproducible packaging:
- Object keys sorted lexicographically; arrays in defined sort orders (`resources` by
  `(kind,resourceId)`; `tags` sorted; `checksums.files` by `path`).
- No runtime-generated values: `createdAt`/`signedAt` are **inputs**, never `now()`.
- UTF-8, LF newlines, no trailing whitespace, numbers in shortest round-trip form.

Full canonicalization rules are shared with
[design-import-export-specification.md §Export](design-import-export-specification.md).

---

## 15. Minimal manifest example

```json
{
  "schema": "tncms.design-package",
  "version": "2.0",
  "metadata": {
    "id": "acme.landing", "name": "Acme Landing", "contentVersion": "2.1.0",
    "source": "premium", "vendor": { "id": "acme", "name": "Acme Studio" }
  },
  "resources": [
    { "kind": "section", "resourceId": "hero-split", "version": "2.1.0", "category": "hero",
      "name": "Split Hero", "entitlement": "free", "digest": "sha256-…" },
    { "kind": "page", "resourceId": "landing-home", "version": "2.1.0", "category": "landing",
      "name": "Landing Home", "entitlement": "premium", "digest": "sha256-…" }
  ],
  "compatibility": { "schemaVersion": "1", "builder": ">=1.0 <2.0" },
  "license": { "id": "commercial", "file": "LICENSE" },
  "checksums": { "algorithm": "sha-256", "files": [ { "path": "resources/section/hero-split.json", "digest": "sha256-…", "bytes": 812 } ] }
}
```

---

## 16. Conformance checklist

1. `schema`/`version` fixed; `metadata` complete; one `source` (§1–§2).
2. `resources[]` mirrors files; digests match `CHECKSUMS` (§3, §13).
3. Fonts/icons/tokens/locales/preview indexed with digests for bundled files (§4–§8).
4. Compatibility + dependencies declared for gating (§9, design-package-v2 §5).
5. License/marketplace/signature isolated from runtime (§10–§12).
6. Canonical, deterministic serialization (§14).

---

*End of design-package-manifest.md*
