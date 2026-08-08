# Design Import / Export Specification (Phase 26.40B)

> **Type:** Technical Specification (normative). **No production code.**
> Defines the **import** pipeline (Step 11) and the **deterministic export** pipeline
> (Step 12) for `tncms.design-package` v2. Reuses the Phase 26.26 validation-first/atomic
> importer spine — described here as an extension, not a rewrite.
>
> Related: [design-package-v2.md](design-package-v2.md),
> [design-package-manifest.md](design-package-manifest.md),
> [design-resource-schema.md](design-resource-schema.md),
> [design-version-compatibility.md](design-version-compatibility.md),
> [licensing-boundary.md](licensing-boundary.md).
>
> **Conventions:** RFC 2119 keywords. "Atomic" = all-or-nothing; on any blocking failure the
> resolved library is left byte-identical to its pre-import state.

---

## Part A — Import (Step 11)

### A.1 Pipeline stages (ordered, fail-fast on blocking)

```
1 Parse/unpack        → tree + manifest
2 Integrity           → CHECKSUMS digests, (optional) signature
3 Structural validate → manifest + each resource (schema)
4 Asset boundary      → URL-safety; no storage ids
5 Compatibility       → PackageCompatibility + per-resource C7  → reasons/warnings
6 Dependencies        → resolve graph                            → reasons/warnings
7 Migration           → schema/version upgrade if needed (seam)
8 Entitlement         → EntitlementResolver at install
9 Conflict/duplicate  → decide per RID+version
10 Commit (atomic)    → rehost assets, land resources, tokens, locales; record ledger
```

Stages 1–9 are **read-only** (no library mutation). Only stage 10 mutates, and it is atomic.

### A.2 Validation classes → result channel

Every check yields either a **blocking `reason`** or a **non-blocking `warning`**, reusing
the Phase 26.27 `{ compatible, reasons[], warnings[] }` shape, extended to a package
result:

```ts
interface ImportResult {
  ok: boolean
  packageId: string
  resourceIds: string[]        // RIDs landed (empty if !ok)
  reasons: ImportIssue[]       // blocking — present iff !ok
  warnings: ImportIssue[]      // non-blocking — MAY be present when ok
}
interface ImportIssue { code: string; message: string; rid?: string; path?: string }
```

`code` values are invariant KEYS (machine-stable), `message` is localizable display text.

### A.3 Integrity (stage 2)

- Every file digest MUST match `CHECKSUMS`; a mismatch is a blocking `reason`
  (`integrity.digest_mismatch`).
- Signature verification (if the manifest carries one) follows the source trust policy:
  community → advisory (`warning` on failure), premium/marketplace → required-configurable
  (`reason` on failure when policy = required). Signing is a **seam** (design-package-manifest §12).

### A.4 Structural + asset boundary (stages 3–4)

- Resources validated against [design-resource-schema.md §10](design-resource-schema.md):
  required fields, one-of `tree`/`members`, id-free, 2-level, JSON-safe.
- Asset boundary: any unsafe URL scheme or embedded storage/attachment id → blocking
  `reason` (`asset.unsafe_url`, `asset.storage_id_forbidden`).

### A.5 Compatibility (stage 5)

- Evaluate package `compatibility` and each resource's C7 against the `BuilderEnvironment`
  (`builderVersion`, `components`, `features`).
- Missing **known-required** component/feature or out-of-range builder → blocking `reason`.
- Unverifiable/unknown requirement → non-blocking `warning`.
- Full rules + matrix: [design-version-compatibility.md](design-version-compatibility.md).

### A.6 Dependencies (stage 6)

- Resolve per [design-package-v2.md §5](design-package-v2.md): satisfied / missing-required
  (reason) / out-of-range-required (reason) / optional-missing (warning) / cycle (reason).
- Resolution is deterministic and MUST NOT fetch remotely in the core importer.

### A.7 Migration (stage 7)

- If the package/resource declares an older `schemaVersion`/format the builder can upgrade,
  route it through the migration provider. Today `nullMigrationProvider` (26.27) is the seam;
  a v1 `tncms.template-package` is accepted by mapping `templates[]` → `page`/`section`
  resources (design-package-v2 §6). An unsupported version with no migrator is a blocking
  `reason` (`version.unsupported`).

### A.8 Entitlement (stage 8)

- Consult `EntitlementResolver.can(...)` for gated resources/packs **before** commit.
  Not entitled → blocking `reason` with an actionable code (`entitlement.requires_upgrade`
  / `entitlement.requires_purchase` / `entitlement.expired`).
- Entitlement is enforced here (and at apply/update), never at discovery/preview. See
  [licensing-boundary.md](licensing-boundary.md).

### A.9 Conflict, duplicate, version-mismatch (stage 9)

For each incoming resource RID vs the resolved library:

| Situation | Rule |
|---|---|
| No existing RID | Install |
| Existing RID, **same** version, **same** digest | **Duplicate** → idempotent no-op (skip, `warning` `duplicate.skipped`) |
| Existing RID, same version, **different** digest | **Conflict** → blocking `reason` `conflict.version_content_differs` (unless caller opts into an explicit overwrite policy) |
| Existing RID, **different** version | **Version mismatch** → policy-driven: default = keep both if the library supports multi-version; else newer-wins with a `warning`; downgrade requires explicit opt-in |

- The conflict policy is an **input** to import (e.g. `skip` | `overwrite` | `keep-both` |
  `fail`), defaulting to `fail` on content conflict for safety. Whatever the policy, the
  outcome MUST be deterministic and atomic.

### A.10 Commit (stage 10, atomic)

In one atomic unit:
1. Rehost bundled assets → URLs (idempotent by content; design-package-v2 §4.3).
2. Land resources into the correct backend source:
   - `block`/`section`/`page` → `ImportedTemplateDefinitionSource` + publish metadata to
     `importedTemplateLibrary`.
   - `kit` → starter/kit registry (references only); token sets applied via `TokenRegistry`.
3. Register localization tables; index preview assets.
4. Record the install in the ledger (`InstalledPack`, references only — 26.30/26.31).

If any sub-step fails, the whole commit rolls back; `ok=false`, `resourceIds=[]`, and the
library is byte-identical to its pre-import state.

### A.11 Rollback guarantees

- No partial installs: a package either fully lands or not at all.
- Rehosting performed during a failed commit MUST be reverted or safely orphaned (content-
  addressed assets MAY be retained since they are id-free and de-duplicated).
- The ledger is updated only on successful commit.

---

## Part B — Export (Step 12)

Export produces a **deterministic, reproducible** package: the same input MUST yield a
byte-identical archive (modulo externally supplied timestamps), enabling stable checksums
and signatures.

### B.1 Inputs

- The set of resources (by RID) to include (+ transitively required members for kits).
- Package metadata (`id`, `name`, `contentVersion`, `source`, author/vendor, license).
- Externally supplied `createdAt`/`signedAt` (NOT generated during the build).
- Optional signing key (seam).

### B.2 Deterministic serialization (normative)

All emitted JSON (manifest, resources, tokens, locales) MUST use **canonical form**:
1. UTF-8, LF newlines, no BOM, no trailing whitespace.
2. Object keys sorted lexicographically (byte order).
3. Arrays in defined sort orders:
   - `resources[]` by `(kind, resourceId)`;
   - `tags[]` ascending; `requiredComponents`/`requiredFeatures` ascending;
   - `checksums.files[]` by `path` ascending; `members[]` in author-declared order (kits are
     ordered) but de-duplicated.
4. Numbers in shortest round-trip decimal; no `-0`; no `NaN`/`Infinity`.
5. No insignificant whitespace (single canonical spacing) — one agreed pretty/compact form
   used consistently.
6. Resource trees are normalized: **strip all ids**, deep JSON-clone (drop functions/
   undefined), preserve asset URLs verbatim (mirrors Phase 26.26 `normalizeDefinition`).

### B.3 Asset collection

- Bundled assets referenced by exported resources are collected under `assets/`/`fonts/`/
  `icons/`/`preview/` with **content-addressed** filenames (or stable declared paths), so the
  same bytes always land at the same path.
- External URLs are kept verbatim (not downloaded) unless the exporter is explicitly asked to
  bundle them.
- Storage/attachment ids MUST NOT appear anywhere; only URLs / package-relative paths.

### B.4 Checksums & signature

- Compute `sha-256` per file; write `CHECKSUMS` (sorted by path) and mirror into
  `manifest.checksums`.
- If signing, compute the signature over the canonical manifest (which includes the checksum
  index), producing a single detached `signature` protecting all bytes.

### B.5 Reproducibility guarantee

Given identical inputs (including supplied timestamps and key), export MUST produce a
byte-identical archive. No `now()`, no `random()`, no hash-map iteration order, no locale-
dependent formatting may leak into output. This makes packages diffable, cacheable, and
verifiable (and is the basis for [design-ai-compatibility.md](design-ai-compatibility.md)
validation).

### B.6 Round-trip invariant

`import(export(R)) ≡ R` for the resource payload (C1–C7): a resource exported and re-imported
(on a compatible builder) MUST insert byte-identically. C8 (distribution) MAY differ by
channel and is excluded from this invariant (resource-spec §7).

---

## Part C — Shared determinism rules

The determinism rules in B.2/B.5 are shared by:
- dependency resolution (design-package-v2 §5.3),
- token resolution (design-token-specification §9),
- preview/sample rendering (design-preview-specification §5),
- AI-generated package validation (design-ai-compatibility).

Any pipeline that claims conformance MUST be deterministic under these rules.

---

## Conformance checklist

1. Import is staged, read-only until an atomic commit; blocking vs warning channel used (A.1–A.2).
2. Integrity, structural, asset-boundary, compatibility, dependency, entitlement checks all
   applied in order (A.3–A.8).
3. Conflict/duplicate/version-mismatch resolved deterministically per policy (A.9).
4. Commit lands resources in correct backends; full rollback on failure (A.10–A.11).
5. Export is canonical, content-addressed, reproducible; ids stripped; URLs preserved (B).
6. `import(export(R)) ≡ R` for C1–C7 (B.6).

---

*End of design-import-export-specification.md*
