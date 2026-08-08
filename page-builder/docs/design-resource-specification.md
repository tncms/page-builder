# Design Resource Specification (Phase 26.40B)

> **Type:** Technical Specification. **No production code.** This document is normative:
> multiple developers should be able to implement compatible results from it alone.
> It refines the approved architecture in
> [design-library-architecture.md](design-library-architecture.md) and
> [design-resource-model.md](design-resource-model.md) (Phase 26.40A) into an exact contract.
>
> **Companion 26.40B specs:** [design-resource-schema.md](design-resource-schema.md),
> [design-package-v2.md](design-package-v2.md),
> [design-package-manifest.md](design-package-manifest.md),
> [design-token-specification.md](design-token-specification.md),
> [design-preview-specification.md](design-preview-specification.md),
> [design-import-export-specification.md](design-import-export-specification.md),
> [design-version-compatibility.md](design-version-compatibility.md),
> [design-marketplace-metadata.md](design-marketplace-metadata.md),
> [design-ai-compatibility.md](design-ai-compatibility.md).

**Conventions.** The key words MUST, MUST NOT, REQUIRED, SHOULD, SHOULD NOT, MAY are used
as in RFC 2119. "Invariant KEY" = a machine-stable identifier that MUST NOT be localized or
renamed across versions. All examples are illustrative; no code is prescribed.

---

## 1. Scope & Frozen Constraints

A **Design Resource** is the atomic unit the Design Library lists, previews, packages,
distributes, and inserts. This document defines its **identity**, **classification
(kind)**, and **field categories** — the formal per-field schema is in
[design-resource-schema.md](design-resource-schema.md).

The following are **frozen** (Phase 26.40) and a Design Resource MUST NOT alter them:

- Runtime document model: `LayoutPayload {schema?, sections: SectionNode[]}` →
  `SectionNode {type, id?, data, blocks: BlockNode[]}` → `BlockNode {type, id?, data}`.
  Strictly **2-level** (section → block).
- The only document mutation is the existing `insert-pattern` command (and `applyTemplate`
  which wraps it). One command = one undo. The server re-keys ids on insert.
- A resource's layout payload MUST be an **id-free tree** (no `id` on section/block nodes).
- Assets are **URL-identity only** — no host storage/attachment ids (see
  [design-package-v2.md §Assets](design-package-v2.md)).

---

## 2. Field Categories (overview)

Every Design Resource is composed of eight **field categories**. Each category has an owner
document for exhaustive detail; this list is the canonical taxonomy.

| # | Category | Purpose | Detailed in |
|---|---|---|---|
| C1 | **Identity** | Globally unique, stable addressing | §3 (this doc) |
| C2 | **Classification** | `kind`, `category`, `tags` | §4 (this doc) |
| C3 | **Descriptive** | `name`, `description`, `author`, `vendor` | schema doc |
| C4 | **Payload** | id-free `tree` (block/section/page) or `members` (kit) | schema doc |
| C5 | **Preview** | thumbnail / runtime / device / sample content | [design-preview-specification.md](design-preview-specification.md) |
| C6 | **Tokens** | design-token references + fallbacks | [design-token-specification.md](design-token-specification.md) |
| C7 | **Compatibility** | builder/schema/component/feature requirements | [design-version-compatibility.md](design-version-compatibility.md) |
| C8 | **Distribution** | `source`, `entitlement`, provenance, marketplace metadata | [design-marketplace-metadata.md](design-marketplace-metadata.md) |

> **Separation rule (normative).** C1–C7 describe *what the resource is* and are identical
> across every edition and channel. C8 describes *how it is distributed/licensed* and MUST
> NOT change the resource's runtime behavior or payload. FREE, PRO, community, and premium
> copies of the same resource have **identical** C1–C7 and differ only in C8. See
> [licensing-boundary.md](licensing-boundary.md).

---

## 3. Identity System (Step 2)

### 3.1 Canonical identifier

Every Design Resource has a canonical id (the **RID**, Resource IDentifier):

```
RID = "<source>:<kind>:<resourceId>"
```

- `source` — invariant source KEY (§3.3).
- `kind` — invariant classification KEY (§4.1).
- `resourceId` — a **slug** unique within `(source, kind)` (§3.2).

Examples: `builtin:section:hero-split`, `imported:page:landing-home`,
`premium:kit:agency-suite`.

**Rationale.** The Design Library spans multiple kinds across the same sources; without
`kind` in the identity, a `section` and a `page` could collide on `resourceId` within one
source. Encoding `source` + `kind` also makes the RID **self-describing** (a reader can
route it without a lookup) and stable for favorites, installed-ledgers, provenance, and
dependency references.

**Relationship to 26.21.** The existing `TemplateLibraryItem.id` is `<source>:<templateId>`.
When surfaced as a Design Resource, the facade projects it to `<source>:<kind>:<templateId>`
(e.g. `builtin:hero` → `builtin:page:hero`). The underlying Template Library id is
unchanged; the RID is the Design-Library-level identity.

### 3.2 `resourceId` slug rules (normative)

- Charset: `[a-z0-9]` plus single internal hyphens: regex `^[a-z0-9]+(-[a-z0-9]+)*$`.
- Length: 1–64 characters.
- Case: lowercase only; producers MUST lowercase; consumers MUST treat as case-sensitive
  after that (i.e. do not re-case).
- MUST be unique within `(source, kind)`. Cross-`kind` reuse of the same slug is allowed
  (`builtin:section:hero` and `builtin:page:hero` are distinct RIDs).
- MUST be stable for the life of the resource. Renaming a resource's `resourceId` is a
  **new resource**, not an edit — see the rename policy in
  [design-version-compatibility.md](design-version-compatibility.md).

### 3.3 `source` KEYS (normative)

Invariant source KEYS, unifying Template Library (26.21) and Distribution (26.33):

| KEY | Status | Meaning |
|---|---|---|
| `builtin` | active | First-party, ships with the builder |
| `user` | active | The user's own saved resources (local authorship) |
| `imported` | active | Imported from an offline package |
| `community` | additive | Community-contributed (typically free); transport MAY be `remote`/`imported` |
| `premium` | additive | Commercial/premium; transport MAY be `marketplace`/`remote` |
| `marketplace` | reserved | Future commercial marketplace |
| `remote` | reserved | Future remote/community directory |
| `cloud` | reserved | Future cloud library |

`community` and `premium` are **display/policy facets** resolved by entitlement/trust; the
underlying transport reuses reserved keys. Producers MUST NOT invent new `source` KEYS.

### 3.4 Global uniqueness & collisions

- A RID MUST be globally unique within a single builder installation's resolved library.
- On import, a RID collision with an existing resource of the **same version** is a
  **duplicate** (idempotent no-op); a collision with a **different version** is a **conflict**
  resolved per [design-import-export-specification.md §Import](design-import-export-specification.md).
- Package-internal references (dependencies, kit `members`) use RIDs or short intra-package
  ids that resolve to RIDs at import (see [design-package-v2.md](design-package-v2.md)).

### 3.5 Versioned identity

The RID identifies the resource across versions; the **version** is carried separately in
C7/manifest (`version`, semver `X.Y.Z`). A specific pinned reference is written
`RID@version` (e.g. `premium:kit:agency-suite@2.1.0`) and is used only in dependency pins
and lockfiles, never as the resource's primary key. Version/semver semantics are defined in
[design-version-compatibility.md](design-version-compatibility.md).

---

## 4. Classification (Step 3)

### 4.1 `kind` — the primary discriminator (normative, closed set)

| KEY | Definition | Payload | Runtime insert path |
|---|---|---|---|
| `block` | Single/atomic component snapshot placed into a section | id-free tree (atomic) | `insert-pattern` (into target section) |
| `section` | Multi-block reusable section | id-free `PatternTree` | `insert-pattern` |
| `page` | Full Page Template: production-ready multi-section document | id-free `TemplateDefinition.tree` | `applyTemplate → insert-pattern` |
| `kit` | Grouped resources by reference + tokens/fonts/assets (future) | `members[]` (RID refs) + token payload | scripted sequence of the above |

- `kind` is a **closed set**. Producers MUST NOT introduce new `kind` values. Extending the
  set is a **builder-version** change governed by
  [design-version-compatibility.md](design-version-compatibility.md), never a package-level
  liberty.
- `block` cannot exist at document root (2-level frozen hierarchy); its placement target is
  the selected section (or a default wrapper) per
  [design-resource-model.md §1.1](design-resource-model.md).
- `kit` references members by RID and MUST NOT copy their payloads.

### 4.2 `category` (normative)

- A single invariant category KEY per resource (e.g. `hero`, `cta`, `pricing`, `gallery`,
  `footer`, `kit`, `uncategorized`).
- Default: `uncategorized`.
- Charset/length identical to `resourceId` slug rules (§3.2).
- Categories are a **controlled but open** vocabulary: the builder ships a base set;
  packages MAY introduce a new category KEY, which consumers MUST accept (unknown category →
  shown under its raw KEY, never rejected). Display labels are localized (never the KEY).

### 4.3 `tags` (normative)

- Zero or more invariant tag KEYS; same charset/length as slugs.
- Free/open vocabulary; used for search and filtering only; MUST NOT affect runtime.
- Deduplicated, order-insensitive; producers SHOULD emit them sorted for deterministic
  packaging (see [design-import-export-specification.md](design-import-export-specification.md)).

### 4.4 Classification vs behavior

Classification (C2) is metadata for discovery. It MUST NOT be consulted by the runtime to
alter rendering or insertion. Two resources with identical payloads but different `tags`
MUST render identically.

---

## 5. Resource Lifecycle

```
authored ─▶ packaged (export) ─▶ distributed ─▶ imported ─▶ installed ─▶ previewed ─▶ inserted
   │            │                                   │           │            │            │
 (user/AI)   deterministic                     validation   ledger      runtime      insert-pattern
             + checksums                        + compat +   (refs)      projection   (1 undo)
                                                entitlement                (no commit)
```

- **Authored:** by a user, a first-party designer, or an AI generator (see
  [design-ai-compatibility.md](design-ai-compatibility.md)). Output is an id-free tree +
  metadata.
- **Packaged:** deterministic export with checksums (see
  [design-import-export-specification.md](design-import-export-specification.md)).
- **Imported:** validation-first, atomic, entitlement-gated; lands in the correct backend
  source.
- **Installed:** recorded in the ledger by reference (never copied), as adoption discovery
  already does (26.31).
- **Previewed:** ephemeral, non-committing runtime projection (see
  [design-preview-specification.md](design-preview-specification.md)).
- **Inserted:** copy-on-insert via `insert-pattern`; id re-keyed by the server; the content
  becomes the user's own (no sync link).

---

## 6. Compatibility Fields (C7 overview)

Every resource carries compatibility requirements consumed at import/apply:

- `schemaVersion` — the resource payload/tree schema version (currently `"1"`).
- `builder` — semver **range** of builder versions the resource supports.
- `requiredComponents` — component type KEYS that MUST be registered.
- `requiredFeatures` — feature KEYS that MUST be present.

These reuse Phase 26.27 `PackageCompatibility` + `versionRange`. Gating semantics (blocking
`reasons` vs non-blocking `warnings`) are defined in
[design-version-compatibility.md](design-version-compatibility.md).

---

## 7. Determinism & Equality

- Two resources are **byte-equal** iff their canonical serializations (C1–C7, with C8
  excluded) are identical. C8 (distribution/marketplace/licensing) is **excluded from
  resource identity and equality** — a free and a premium copy of the same design are the
  same resource.
- Canonical serialization rules (key ordering, whitespace, number formatting) are defined in
  [design-import-export-specification.md §Export](design-import-export-specification.md) and
  MUST be applied so that packaging is reproducible and checksums are stable.

---

## 8. Conformance Checklist

An implementation conforms to this specification if:

1. It assigns every resource a RID `<source>:<kind>:<resourceId>` with valid slugs (§3).
2. It treats `kind` as a closed set and `source` KEYS as invariant (§3.3, §4.1).
3. It keeps C1–C7 identical across editions/channels and isolates all distribution/licensing
   data in C8 (§2, §7).
4. It never writes ids into resource trees and never serializes storage/attachment ids (§1).
5. It routes every kind to the frozen `insert-pattern`/`applyTemplate` path only (§4.1).
6. It defers per-field types/validation to [design-resource-schema.md](design-resource-schema.md)
   and cross-cutting rules to the companion specs.

---

*End of design-resource-specification.md*
