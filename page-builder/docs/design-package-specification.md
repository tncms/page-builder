# Design Package Specification — Package v2 (`tncms.design-package`)

> **Type:** Product Architecture. **No production code.** Contracts/specification only.
> Part of the Design Library architecture — see
> [design-library-architecture.md](design-library-architecture.md),
> [design-resource-model.md](design-resource-model.md),
> [design-preview-architecture.md](design-preview-architecture.md),
> [licensing-boundary.md](licensing-boundary.md),
> [product-decisions.md](product-decisions.md).

This document specifies **Package v2**: the single, portable transport format that
**Community, Official, and Premium** design resources all use — differing only by
entitlement and trust policy, never by format. It is a **backward-compatible superset** of
the existing v1 `tncms.template-package` (Phase 26.26).

---

## 1. Goals & Non-Goals

**Goals**
- **One format for all sources and tiers** (community / official / premium / offline / cloud).
- Carry **every resource kind** (`block`, `section`, `page`, `kit`) plus **assets, fonts,
  icons, localization, and preview assets**.
- **Portable:** no host storage ids, no attachment ids — URL-identity only.
- **Validation-first + atomic import** (reuse the v1 importer spine).
- **Backward compatible** with v1 packages.
- **Seller/marketplace-ready:** signatures, dependencies, compatibility, entitlement — as
  **seams** (contract-only this phase).

**Non-goals**
- No payments/storefront.
- **No executable code** in packages (markup + styles + metadata + asset descriptors only).
- No change to Builder serialization, runtime, CommandBus, or BuilderState.
- No signing or i18n *infrastructure* built now — signatures and localization are declared
  seams.

---

## 2. Manifest Schema (annotated)

```jsonc
{
  "schema": "tncms.design-package",     // NEW schema id (v1 was "tncms.template-package")
  "version": "2.0",                     // package format version

  "metadata": {
    "id": "acme.landing-kit",           // invariant KEY, globally unique
    "name": "Acme Landing Kit",
    "description": "Marketing landing sections + a full page.",
    "author": "Acme Studio",
    "license": "MIT",                   // human license string (informational)
    "homepage": "https://acme.example/kits/landing",
    "tags": ["marketing", "landing"]    // invariant KEYS
  },

  // Every distributable artifact. `kind` selects how it lands + inserts.
  "resources": [
    {
      "id": "hero-split",               // unique within the package
      "kind": "section",                // block | section | page | kit
      "name": "Split Hero",
      "category": "hero",               // invariant KEY
      "tags": ["hero", "split"],
      "entitlement": "free",            // classification (badge) — NOT a gate; see licensing-boundary.md
      "tree": { /* id-free PatternTree: sections/blocks with `data` only, no `id` */ },
      "preview": { "thumbnail": "assets/hero-split.webp" }  // package-relative or URL
    },
    {
      "id": "landing-home",
      "kind": "page",                   // Full Page Template = production-ready document
      "name": "Landing — Home",
      "category": "landing",
      "entitlement": "premium",
      "tree": { /* id-free multi-section TemplateDefinition.tree */ }
    },
    {
      "id": "landing-kit",
      "kind": "kit",                    // groups members by REFERENCE + carries tokens/fonts/assets
      "name": "Landing Kit",
      "category": "kit",
      "members": ["hero-split", "landing-home"],   // resource id references (never copies)
      "entitlement": "premium"
    }
  ],

  // Bundled assets. URL-identity or PACKAGE-RELATIVE path (resolved to a URL on import).
  "assets": [
    { "id": "hero-bg", "path": "assets/hero-bg.webp", "type": "image", "width": 1600, "height": 900 },
    { "id": "cdn-logo", "url": "https://cdn.acme.example/logo.svg", "type": "image" }
  ],

  "fonts": [
    { "family": "Inter", "weights": [400, 600, 700], "src": "fonts/inter-var.woff2", "display": "swap" }
  ],

  "icons": [
    { "set": "acme-icons", "src": "icons/acme.svg" }   // or references to a registered icon set
  ],

  // Per-locale DISPLAY strings only. KEYS (category/tag/source/kind) stay invariant.
  "localization": {
    "en": { "resources.hero-split.name": "Split Hero" },
    "vi": { "resources.hero-split.name": "Hero chia đôi" }
  },

  // Thumbnails / preview media referenced by resources (URL-only, sanitized).
  "previewAssets": [
    { "id": "hero-split-thumb", "path": "assets/hero-split.webp" }
  ],

  "compatibility": {                    // reuse Phase 26.27 PackageCompatibility + schemaVersion
    "schemaVersion": "1",               // template tree schema version (SUPPORTED_TEMPLATE_SCHEMA_VERSIONS)
    "builder": ">=1.0 <2.0",            // semver range (versionRange)
    "requiredComponents": ["section.hero", "block.button"],
    "requiredFeatures": ["responsive"]
  },

  // References to other packages/resources (for kits sharing resources).
  "dependencies": [
    { "packageId": "acme.core-blocks", "version": ">=1.2 <2.0" }
  ],

  // Detached signature over a canonical serialization. SEAM (contract-only now).
  "signature": {
    "algorithm": "ed25519",
    "keyId": "acme-2026",
    "value": "base64…"
  }
}
```

### 2.1 Field notes

- **`resources[].tree`** is an **id-free** `PatternTree` (data only, no `id`) for
  `block`/`section`/`page`; `kind: 'kit'` uses **`members`** (id references) instead of a
  tree and never copies member definitions.
- **`entitlement`** is a **classification for badging**, not an access gate. The gate is the
  `EntitlementResolver` at import/install — see [licensing-boundary.md](licensing-boundary.md).
- **`assets[]`** may use `url` (already a URL) or `path` (**package-relative**). See §5 for
  how package-relative paths are resolved to URLs on import while preserving URL-identity.
- **`localization`** localizes **display strings only**; all machine KEYS remain invariant.
- **`compatibility`** and **`dependencies`** drive gating (§3, §4).
- **`signature`** trust model in §2.2.

### 2.2 Signatures (trust seam)

Signing is a **seam** (declared, not implemented this phase; mirrors the contract-only
`nullMigrationProvider`). Trust policy by source:

| Source | Signature | Verification |
|---|---|---|
| `community` | self-signed / optional | **advisory** (warn if unverifiable) |
| official / `builtin` | signed by the vendor key | recommended-required |
| `premium` / `marketplace` | signed by seller/vendor key | **required-configurable** (host policy) |
| `imported` (offline) | may be unsigned | advisory; still resolver-checked at install |

Verification produces the same **blocking `reasons` vs non-blocking `warnings`** shape as
compatibility (§3), so the importer treats trust and compatibility uniformly.

---

## 3. Versioning & Migration

### 3.1 Schemas & versions accepted

- `SUPPORTED_PACKAGE_SCHEMAS` extends to accept **both** `tncms.template-package` (v1) and
  `tncms.design-package` (v2).
- `SUPPORTED_PACKAGE_VERSIONS` extends to accept `1.0` (v1) and `2.0` (v2).
- Template **tree** schema versions continue via 26.27 `SUPPORTED_TEMPLATE_SCHEMA_VERSIONS`
  (`['1']`) and `BUILDER_VERSION='1.0.0'` semver checks (`versionRange`).

### 3.2 v1 → v2 upgrade path

A v1 `tncms.template-package` is a valid input. It upgrades transparently:

- Each v1 `templates[]` entry becomes a v2 `resources[]` entry with
  **`kind: 'page'`** (a whole `TemplateDefinition` is a Full Page Template) — or `section`
  when the definition is a single section. The mapping is deterministic and additive.
- v1 has no assets/fonts/icons/localization/preview/dependencies/signature → those v2
  sections are simply absent (all optional).
- **Migration seam:** today's `nullMigrationProvider` (26.27,
  `TemplateMigrationProvider {canMigrate(from,to), migrate(pkg)}`) is the hook where a real
  v1→v2 migrator attaches. Until implemented, v1 packages are accepted directly by treating
  their entries as `page` resources (no transformation needed).

### 3.3 Compatibility matrix

| Package | schema | version | Import behavior |
|---|---|---|---|
| v1 | `tncms.template-package` | `1.0` | Accepted; entries → `page`/`section` resources |
| v2 | `tncms.design-package` | `2.0` | Accepted natively |
| Unknown schema | — | — | Rejected (validation reason) |
| Unknown version | supported schema | — | Rejected, or routed to migration seam when available |

### 3.4 Backward-compatibility & deprecation

- v1 remains **fully supported** (no forced migration). Export may continue to emit v1 for
  template-only content, or v2 for anything richer.
- Deprecation, when it happens, follows the existing
  [page-builder-versioning-and-deprecation-policy.md](page-builder-versioning-and-deprecation-policy.md)
  (SemVer, deprecation window). v2 is **additive** — no breaking change to v1 consumers.

---

## 4. Import Pipeline

Reuses the v1 spine (26.26) — **described as an extension, not a rewrite**:

1. **Parse** JSON → package object.
2. **Validate whole package first** (before registering anything):
   - schema + version supported;
   - metadata has `id` + `name`;
   - at least one resource; unique resource ids;
   - per resource: valid `id`/`name`/`category`; `tree` present for tree-kinds; `members`
     resolve for `kind: 'kit'`;
   - **asset boundary** (§5): no host storage ids, no unsafe URL schemes;
   - **compatibility gate** (26.27): blocking `reasons` vs non-blocking `warnings`;
   - **dependencies** (§4.1);
   - **signature/trust** (§2.2), per host policy.
3. **Atomic decision:** any blocking issue → **register nothing** (all-or-nothing), return a
   structured `TemplatePackageError`-style `{issues:[{code,message,resourceId?}]}`.
4. **Entitlement gate** (import/install): consult `EntitlementResolver`; not entitled →
   block with an actionable upgrade/purchase reason (same reason shape). See
   [licensing-boundary.md](licensing-boundary.md).
5. **Land resources into the correct existing backends:**
   - `page`/`section`/`block` → `ImportedTemplateDefinitionSource` (source `imported`) +
     publish metadata to `importedTemplateLibrary`;
   - `kit` → the starter/kit registry (references-only), tokens registered via
     `TokenRegistry`;
   - assets/fonts/icons resolved to URLs (§5); localization strings registered for display.
6. **Result:** `{ ok, packageId, resourceIds[], warnings[] }` (warnings non-blocking, e.g.
   unverifiable signature or unknown-but-optional capability).

### 4.1 Dependencies

- `dependencies[]` reference other packages/resources by `id` + version range.
- Resolution mirrors 26.27 gating: **missing known-required dependency → blocking reason**;
  **unverifiable/unknown → non-blocking warning**.
- Kits use dependencies to reference shared resources without copying them (consistent with
  references-only packs).

---

## 5. Asset Boundary (critical)

**Resources and trees NEVER carry media ids, attachment ids, or storage paths — only
URLs.** This is what makes packages portable and is enforced at validation.

- `isSafeAssetUrl` policy is restated: **http / https / relative** only; reject
  `javascript:` / `data:` / `file:` / `vbscript:` and empty. `sanitizeThumbnail` applies to
  all preview URLs.
- **Bundled (package-relative) assets** (`assets[].path`, `fonts[].src`, `previewAssets[].path`):
  on import, the host **rehosts** the bytes into its own media/asset storage and **resolves
  each to a URL**, which is what gets written into resource trees. The **rehosting seam**
  converts a package-relative path → a safe URL; the resource tree only ever stores the
  resulting URL, so the URL-identity invariant holds end-to-end.
- The host media **id** produced by rehosting is **display-only** and is **never serialized**
  into a tree (consistent with `AssetReference.toMediaResult() → {src, alt?}`).

> Net effect: a package may *ship* bytes, but once imported, every reference in a resource
> tree is a plain URL. Export of a user resource likewise emits URLs (or re-bundles bytes as
> package-relative assets), never storage ids.

---

## 6. Summary

| Concern | v2 decision |
|---|---|
| Schema / version | `tncms.design-package` / `2.0` (v1 `tncms.template-package`/`1.0` still accepted) |
| Resource kinds | `block` · `section` · `page` · `kit` (kit = members by reference + tokens) |
| Rich payloads | assets · fonts · icons · localization · previewAssets |
| Compatibility | reuse 26.27 `PackageCompatibility` + `versionRange`; reasons vs warnings |
| Dependencies | resource/pack references + version ranges; missing-known → block, unknown → warn |
| Signature | seam (contract-only); trust policy per source |
| Migration | `nullMigrationProvider` hook; v1 entries → `page`/`section` resources |
| Import | validation-first, atomic, entitlement-gated, lands in existing backends |
| Asset boundary | URL-identity only; package-relative bytes rehosted → URL; storage ids never serialized |
| Backward compat | v1 fully supported; v2 additive; SemVer/deprecation policy applies |

---

*End of design-package-specification.md*
