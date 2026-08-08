# Design Resource Schema (Phase 26.40B)

> **Type:** Technical Specification (normative, field-level). **No production code.**
> The formal, exhaustive schema for a Design Resource. Narrative/identity/classification
> rules are in [design-resource-specification.md](design-resource-specification.md);
> cross-cutting details live in the companion 26.40B specs linked there.
>
> **Conventions:** RFC 2119 keywords. Types use TypeScript-ish notation for clarity; the
> wire format is JSON. "Invariant KEY" = machine-stable, never localized/renamed.

---

## 1. Top-level shape

```ts
// Illustrative — specification only.
interface DesignResource {
  // C1 Identity
  rid: string                 // "<source>:<kind>:<resourceId>"  (derived; canonical)
  source: string              // invariant source KEY
  kind: 'block' | 'section' | 'page' | 'kit'
  resourceId: string          // slug, unique within (source, kind)
  version: string             // semver X.Y.Z

  // C2 Classification
  category: string            // invariant KEY, default "uncategorized"
  tags?: string[]             // invariant KEYS

  // C3 Descriptive
  name: string                // display; localizable
  description?: string        // display; localizable
  author?: ResourceAuthor
  vendor?: ResourceVendor

  // C4 Payload (exactly one of tree | members, per kind)
  tree?: PatternTree          // block | section | page  (id-free)
  members?: string[]          // kit: RID references

  // C5 Preview (see design-preview-specification.md)
  preview?: ResourcePreview

  // C6 Tokens (see design-token-specification.md)
  tokens?: TokenBinding[]

  // C7 Compatibility (see design-version-compatibility.md)
  compatibility?: ResourceCompatibility

  // C8 Distribution/licensing (see design-marketplace-metadata.md) — excluded from identity/equality
  distribution?: ResourceDistribution

  // Localization of display strings (see §9)
  localization?: LocalizationTable
}
```

Exactly **one** of `tree` / `members` MUST be present, determined by `kind` (§4).

---

## 2. Field catalog (normative)

Legend — **Req**: R = required, O = optional, D = derived. **Loc**: yes = localizable
display string (KEY stays invariant). **Runtime**: whether the field may influence runtime
(all C8 = no).

| Field | Type | Req | Default | Loc | Runtime | Validation |
|---|---|---|---|---|---|---|
| `rid` | string | D | — | no | no | Equals `source:kind:resourceId`; recomputed, never trusted from input |
| `source` | string | R | — | no | no | ∈ source KEY set (spec §3.3) |
| `kind` | enum | R | — | no | yes* | ∈ {block,section,page,kit} (closed set) |
| `resourceId` | string | R | — | no | no | `^[a-z0-9]+(-[a-z0-9]+)*$`, len 1–64 |
| `version` | string | R | — | no | no | semver `^\d+\.\d+\.\d+$` |
| `category` | string | R | `uncategorized` | no | no | slug rules; unknown accepted |
| `tags` | string[] | O | `[]` | no | no | each slug rules; deduped; sorted on export |
| `name` | string | R | — | yes | no | 1–120 chars; non-empty after trim |
| `description` | string | O | — | yes | no | ≤ 2000 chars |
| `author` | ResourceAuthor | O | — | partial | no | §5 |
| `vendor` | ResourceVendor | O | — | partial | no | §5 |
| `tree` | PatternTree | cond | — | no | yes | §3; id-free; present iff kind ∈ {block,section,page} |
| `members` | string[] | cond | — | no | yes | §4; each a valid RID; present iff kind = kit |
| `preview` | ResourcePreview | O | — | partial | no | preview spec |
| `tokens` | TokenBinding[] | O | `[]` | no | yes | token spec |
| `compatibility` | ResourceCompatibility | O | — | no | gate | §7 |
| `distribution` | ResourceDistribution | O | — | partial | **no** | marketplace spec; excluded from identity |
| `localization` | LocalizationTable | O | — | — | no | §9 |

\* `kind` influences which *insert path* is used; it never alters rendering of the tree
itself.

---

## 3. Payload — `PatternTree` (C4, tree-kinds)

The payload for `block`/`section`/`page` is an **id-free** tree mirroring the frozen runtime
model but WITHOUT `id`:

```ts
interface PatternBlockSnapshot   { type: string; data: Record<string, unknown> }              // no id
interface PatternSectionSnapshot { type: string; data: Record<string, unknown>; blocks: PatternBlockSnapshot[] } // no id
type PatternTree = { schema?: string; sections: PatternSectionSnapshot[] }
```

Validation (normative):
- MUST be JSON-serializable (no functions/undefined/circular refs).
- MUST NOT contain any `id` field on any node.
- Hierarchy MUST be exactly 2-level: `sections[] → blocks[]`. A block MUST NOT contain
  `blocks`.
- `type` MUST be a non-empty string (a component type KEY). Unknown types are handled by the
  compatibility gate (§7), not rejected at schema level.
- `data` values referencing assets MUST be URLs (or package-relative paths that resolve to
  URLs on import) — never host storage/attachment ids. See
  [design-package-v2.md §Assets](design-package-v2.md).
- For `block`, `sections` MUST contain the atomic content in a form the insert path can place
  into a target section (see [design-resource-model.md §1.1](design-resource-model.md)).

`schema` on the tree is the payload schema version (currently `"1"`); see
[design-version-compatibility.md](design-version-compatibility.md).

---

## 4. Payload — `members` (C4, kit)

```ts
members: string[]   // RIDs, e.g. ["builtin:section:hero-split", "premium:page:landing-home"]
```

- Present iff `kind === 'kit'`; `tree` MUST be absent.
- Each entry MUST be a syntactically valid RID (spec §3.1). Members MAY be pinned
  (`RID@version`) for reproducible kits.
- A kit MUST NOT copy member payloads; members are resolved at import/apply. Missing required
  members are a dependency failure (see [design-package-v2.md §Dependencies](design-package-v2.md)).
- A kit MAY additionally carry a token payload via `tokens` (C6) applied through the
  `TokenRegistry`.

---

## 5. Descriptive sub-objects (C3)

```ts
interface ResourceAuthor {
  id?: string          // invariant KEY (stable author handle)
  name: string         // display; localizable
  url?: string         // safe URL (isSafeAssetUrl policy)
}
interface ResourceVendor {
  id: string           // invariant KEY (stable vendor/seller id)
  name: string         // display; localizable
  url?: string         // safe URL
  verified?: boolean   // display-only claim; authoritative verification lives in marketplace metadata
}
```

- `author`/`vendor` are descriptive; the **authoritative** commercial/trust data (verified
  status, ratings) lives in C8 / [design-marketplace-metadata.md](design-marketplace-metadata.md).
- `url` fields MUST pass the URL-safety policy (http/https/relative; reject
  javascript:/data:/file:/vbscript:).

---

## 6. Tokens (C6, summary)

```ts
interface TokenBinding {
  ref: string          // token KEY, e.g. "color.primary", "space.4", "radius.md"
  fallback: unknown    // literal used when the token is unresolved in the active theme
  scope?: string       // optional node/path scope; see token spec
}
```

Full grammar, categories (color/typography/spacing/radius/shadow/animation), resolution, and
theme-compatibility rules are in [design-token-specification.md](design-token-specification.md).
At schema level: every `TokenBinding` MUST provide a `fallback` so a resource renders on a
theme lacking the token.

---

## 7. Compatibility (C7)

```ts
interface ResourceCompatibility {
  schemaVersion: string          // payload schema, e.g. "1"
  builder?: string               // semver range, e.g. ">=1.0 <2.0"
  requiredComponents?: string[]  // component type KEYS
  requiredFeatures?: string[]    // feature KEYS
}
```

- Reuses Phase 26.27 `PackageCompatibility` + `versionRange` (`>=,<=,>,<,=,*`).
- Evaluation produces `{ compatible, reasons[], warnings[] }`: missing **known-required** →
  blocking `reason`; unverifiable/unknown → non-blocking `warning`.
- Absent `compatibility` = unconstrained (compatible), matching v1 behavior.
- Full matrix in [design-version-compatibility.md](design-version-compatibility.md).

---

## 8. Distribution / licensing (C8) — isolation guarantee

```ts
interface ResourceDistribution {
  entitlement?: 'free' | 'pro' | 'premium'  // CLASSIFICATION (badge), not a gate
  license?: string                          // license KEY/SPDX-ish id
  marketplace?: MarketplaceMetadata         // ratings, downloads, pricing, trial — see marketplace spec
  provenance?: { installedFromPack?: string }
}
```

Normative isolation rules:
- C8 MUST NOT influence runtime rendering, insertion, or the resource's canonical
  serialization/equality (spec §7).
- Removing or changing C8 MUST NOT change what gets inserted into a document.
- Entitlement is enforced by the resolver at import/install/update, not by this field. See
  [licensing-boundary.md](licensing-boundary.md) and
  [design-marketplace-metadata.md](design-marketplace-metadata.md).

---

## 9. Localization (`LocalizationTable`)

```ts
type LocalizationTable = {
  [locale: string]: { [displayKey: string]: string }
}
// displayKey addresses a localizable field, e.g. "name", "description",
// "author.name", "preview.caption".
```

- KEYS (`source`, `kind`, `resourceId`, `category`, `tags`, `type`) are **never** localized.
- Only display strings (C3 `name`/`description`, author/vendor names, preview captions) are
  localizable.
- Locale codes follow the CMS locale model (simple lowercase codes). A missing locale falls
  back to the resource's base strings (the untranslated `name`/`description`).
- Localization MUST be **deterministic**: for a given locale the resolved strings are a pure
  function of the table; no runtime/network lookup.

---

## 10. Validation summary (order)

An importer/validator MUST check, in order, failing atomically on the first blocking class
(see [design-import-export-specification.md](design-import-export-specification.md)):

1. **Structural:** required fields present; types correct; exactly one of `tree`/`members`.
2. **Identity:** slug/version/source/kind valid; RID recomputed and consistent.
3. **Payload:** id-free; 2-level; JSON-safe; asset boundary (no storage ids/unsafe URLs).
4. **Tokens:** every binding has a `fallback`.
5. **Compatibility:** evaluate C7 → reasons/warnings.
6. **Distribution/licensing:** structural only; entitlement enforcement happens at
   import/install, not schema validation.

---

## 11. Minimal valid examples

**Section:**
```json
{
  "source": "builtin", "kind": "section", "resourceId": "hero-split", "version": "1.0.0",
  "category": "hero", "tags": ["hero", "split"],
  "name": "Split Hero",
  "tree": { "schema": "1", "sections": [ { "type": "section.hero", "data": {}, "blocks": [] } ] },
  "compatibility": { "schemaVersion": "1", "builder": ">=1.0 <2.0" }
}
```

**Kit (references only):**
```json
{
  "source": "premium", "kind": "kit", "resourceId": "agency-suite", "version": "2.1.0",
  "category": "kit", "name": "Agency Suite",
  "members": ["premium:page:landing-home@2.1.0", "builtin:section:hero-split"],
  "tokens": [ { "ref": "color.primary", "fallback": "#1a1a1a" } ],
  "distribution": { "entitlement": "premium", "license": "commercial" }
}
```

---

*End of design-resource-schema.md*
