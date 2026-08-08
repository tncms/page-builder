# Design Resource Model (Phase 26.40A)

> **Type:** Product Architecture. **No production code.** Contracts/specification only.
> Part of the Design Library architecture — see
> [design-library-architecture.md](design-library-architecture.md),
> [design-preview-architecture.md](design-preview-architecture.md),
> [design-package-specification.md](design-package-specification.md),
> [licensing-boundary.md](licensing-boundary.md),
> [product-decisions.md](product-decisions.md).

This document finalizes the **Design Resource taxonomy**: the unified vocabulary the
Design Library exposes, its metadata envelope, how each kind maps onto the *existing*
Pattern / Template / Starter-Pack backends (which it **reuses, never merges**), and how
each kind reaches the **frozen runtime** through the single existing `insert-pattern`
mutation.

---

## 1. Resource Kinds

A **Design Resource** is any reusable design artifact a user can find in the Design
Library and place into a page. It has exactly one `kind`:

| Kind | Definition | Granularity | Existing backend today |
|---|---|---|---|
| `block` | A single, self-contained component snapshot (one block, or one small block cluster) placed into a section. | Smallest | Editor `PatternDefinition` (atomic) / server `PatternManifest` |
| `section` | A multi-block reusable layout (a full section: hero, pricing table, CTA band). | Section | Pattern Library (`PatternDefinition`, id-free `PatternTree`) |
| `page` | A **Full Page Template** — a production-ready, multi-section Builder document. | Full page | Template Library 26.21 + `TemplateDefinition` + Template Source 26.24 |
| `kit` | *(Future)* A **Starter Kit** — a named, versioned group of resources **plus** design tokens / fonts / assets: a "design system in a box." | Site / system | Extends Starter Pack 26.28–32 (which references templates only) |

> **Why four kinds and not "everything is a template":** blocks, sections, and pages have
> genuinely different *granularity and mental models* for users, and Patterns vs Templates
> already have distinct *behavior* in the codebase (variable systems, in-memory vs
> source-bound storage, manifest vs definition). The `kind` discriminator makes the
> taxonomy explicit for users while letting each backend stay intact. See
> [product-decisions.md §3.1](product-decisions.md) for the rejected "fold into Templates"
> alternative.

### 1.1 The `block` kind and the 2-level hierarchy

The runtime is **strictly 2-level** (`SectionNode → BlockNode[]`); blocks are leaves and
cannot exist at document root. A `block` resource is therefore represented as an id-free
tree that, on insert, is placed **into the currently selected section** (or a default
wrapper section if none is selected) via `insert-pattern`. The resource model does **not**
introduce a root-level block; it defers placement semantics to the existing insert path.
(See Open Questions §7.)

### 1.2 The `kit` kind (future)

A Kit is deliberately **optional** — the common case (drop in one section) never requires
a kit. A Kit:
- **references** member resources by id (never copies their definitions), exactly like a
  Starter Pack references templates;
- additionally **carries design tokens, fonts, and assets** via Package v2 (see
  [design-package-specification.md](design-package-specification.md));
- applies tokens through the Public `TokenRegistry` and inserts members through the same
  runtime path as any other resource.

A 26.28 Starter Pack is, in this model, a **kit-without-tokens** (references only). Kits
supersede Starter Packs additively; no migration is forced.

---

## 2. The Design Resource Envelope

The Design Library projects every backend into one uniform, metadata-only item. It is an
**envelope that points at** an underlying provider — it is **not** new storage and **not**
a runtime node.

```ts
// Illustrative — specification only, not to be implemented this phase.
interface DesignResourceItem {
  id: string            // `<source>:<kind>:<resourceId>`  (see §2.1)
  kind: 'block' | 'section' | 'page' | 'kit'
  source: string        // invariant KEY (see §2.2)
  resourceId: string    // the underlying id within its source/backend

  name: string
  description?: string
  category: string      // invariant KEY (default 'uncategorized')
  tags?: string[]       // invariant KEYS

  thumbnail?: string    // URL only, sanitized (isSafeAssetUrl)
  previewUrl?: string   // URL only, sanitized  — see design-preview-architecture.md

  entitlement?: 'free' | 'pro' | 'premium'   // classification for BADGING, not a gate
  compatibility?: PackageCompatibility        // reuse 26.27
  provenance?: {
    installedFromPack?: string                // pack/kit id, mirrors adoption discovery
  }
}
```

A separate, heavier `DesignResource` (the resolvable thing, carrying or locating the
id-free tree / members) is obtained on demand from the backend when the user previews or
inserts — the library list holds only `DesignResourceItem` metadata, exactly as
`TemplateLibraryItem` (26.21) holds no tree.

### 2.1 Identity: `<source>:<kind>:<resourceId>`

Today `TemplateLibraryItem.id` is `<source>:<templateId>`. Because the Design Library
spans **multiple kinds** across the **same** sources, kind must be part of identity to
keep ids globally unique and self-describing (a `section` and a `page` could otherwise
collide on id within one source). We therefore extend the composite key to
**`<source>:<kind>:<resourceId>`**.

- Backward mapping: an existing `TemplateLibraryItem` id `builtin:hero` becomes
  `builtin:page:hero` (or `builtin:section:hero`) when surfaced as a Design Resource. The
  facade owns this projection; the underlying Template Library id is unchanged.

### 2.2 Sources

Invariant source KEYS, unifying the Template Library (26.21) and Distribution (26.33) sets:

- **Active:** `builtin`, `user`, `imported`.
- **Additive display sources:** `community`, `premium` (may map onto `remote`/`imported`
  transport with an entitlement/trust policy — they are *facets*, resolved by
  [licensing-boundary.md](licensing-boundary.md)).
- **Reserved:** `marketplace`, `remote`, `cloud`.

`source`, `kind`, `category`, and `tag` values are **machine-invariant KEYS** — never
localized. Only display strings localize (via Package v2 localization).

---

## 3. Shared Cross-Kind Concerns

All kinds share one metadata schema and one set of pipelines, which is what makes a single
library, package, and preview possible.

| Concern | Shared rule | Detail owner |
|---|---|---|
| **Metadata** | `DesignResourceItem` (§2) — same fields for every kind; kind-specific extras live in the resolved `DesignResource`/package, not the list item. | this doc |
| **Preview** | URL-only `thumbnail`/`previewUrl` as the fast default + fallback; optional runtime render. | [design-preview-architecture.md](design-preview-architecture.md) |
| **Packaging** | One `tncms.design-package` v2 carries any kind + assets/fonts/icons/localization/preview. | [design-package-specification.md](design-package-specification.md) |
| **Import pipeline** | Validation-first, atomic (reuse 26.26 semantics); lands each kind in its correct backend source. | [design-package-specification.md §4](design-package-specification.md) |
| **Versioning** | Reuse 26.27 `PackageCompatibility` + `versionRange`; blocking `reasons` vs non-blocking `warnings`. | [design-package-specification.md §3](design-package-specification.md) |
| **Entitlement** | `entitlement` tag = classification; badge at discovery/preview, enforce at import/install/update. | [licensing-boundary.md](licensing-boundary.md) |
| **Assets** | URL-identity only; no storage/attachment ids ever serialized. | [design-package-specification.md §5](design-package-specification.md) |

---

## 4. Mapping to Existing Subsystems (Reuse, Not Merge)

The facade **projects** each backend into `DesignResourceItem`; it changes none of them.

| Design kind | Backend (unchanged) | Discovery source | Definition source | Insert |
|---|---|---|---|---|
| `block` | Pattern Library (atomic `PatternDefinition`) / server `PatternManifest` | Pattern picker snapshot | in-memory registry / REST | `insertPattern` |
| `section` | Pattern Library (`PatternDefinition`, id-free `PatternTree`) | Pattern picker snapshot | in-memory registry / REST | `insertPattern` |
| `page` | Template Library 26.21 + `TemplateDefinition` | `templateLibrary` (Composite) | `TemplateSourceResolver` 26.24 | `applyTemplate → insertPattern` |
| `kit` (future) | Starter/Kit registry (26.28 + tokens) | pack discovery / adoption | pack registry (references) + `TokenRegistry` | scripted sequence of the above |

**Non-merge guarantee.** No backend's storage, registry, contract, or insert path is
altered. The facade is a composite read-model (like `CompositeTemplateLibraryProvider`,
one level up). Removing the facade would leave every subsystem fully functional.

Provider seams used (all **Public**, per 26.40): `PatternProvider`, `TemplateProvider`,
`TemplateDefinitionSource`, `TemplateDistributionRegistry`, `TokenRegistry`.

---

## 5. Kind → Frozen Runtime Mapping

The runtime and its mutation surface are **frozen**. Every kind reaches it through the
**one** existing path.

### 5.1 The single mutation

```
resolve resource → id-free tree  →  insertPattern(tree)  →  executeCommand('insert-pattern')
                                                          →  CommandBus (1 command = 1 undo)
                                                          →  server re-keys ids, selects new root
```

- `block` / `section`: resolve to an id-free `PatternTree`, call `insertPattern`. This is
  the existing pattern insert path — unchanged.
- `page`: resolve `TemplateDefinition.tree`, call `applyTemplate` → `insertPattern` (the
  existing 26.23 path: atomic, throws before the command on resolution failure, exactly one
  undo entry).
- `kit` (future): apply tokens via `TokenRegistry`, then insert each member resource
  through its own path above.

### 5.2 Guarantees

- **No new command**, no new node field, no third hierarchy level, no serialization change.
- **Atomic:** resolution failures throw *before* any command; the document is never left
  half-mutated.
- **Copy-on-insert:** the id-free tree is deep-cloned and server-re-keyed; the inserted
  content is the user's own copy (see §6).

### 5.3 Undo granularity

- `block`/`section`/`page`: **one command = one undo** (guaranteed by the existing path).
- `kit`: **open question** — a kit applies multiple resources (and tokens). Options:
  (a) one undo per member (simplest, uses existing commands verbatim); (b) a future
  coalescing wrapper that groups member inserts into a single undo unit. Option (b) must
  *not* introduce a new Frozen-Internal command; it would be a higher-level orchestration
  over existing commands. **Deferred** (see §7). Until decided, kits are one-undo-per-member.

---

## 6. Full Page Template Specifics

A **Full Page Template** (`kind: 'page'`) is a **production-ready Builder document**. The
product promise is: **import once, then replace only your own content.**

- **Structure vs content.** A page template ships a complete section/block **structure**
  with **placeholder/sample content** (headings, copy, image URLs). On insert, that content
  is copied into the user's document and **becomes theirs** to edit.
- **Copy-on-insert, not synced.** We explicitly **reject** Gutenberg-style
  synced/live-linked resources. There is no ongoing link between the template source and the
  inserted content; editing the template later does not change already-inserted pages, and
  editing an inserted page never mutates the template. This is consistent with id-free trees
  + server id re-keying.
- **No serialization change.** A page template's `tree` is an ordinary id-free
  `TemplateDefinition.tree`; inserting it uses `applyTemplate → insertPattern`. The Builder
  document model is untouched.
- **Sample content** is display/starter material only; how it is provided and substituted
  in *preview* is covered in [design-preview-architecture.md §3](design-preview-architecture.md).

---

## 7. Invariants & Non-Goals

**Invariants (must hold for every kind):**
1. URL-only assets; no storage/attachment ids serialized (`isSafeAssetUrl`).
2. Invariant `kind`/`source`/`category`/`tag` KEYS; only display strings localize.
3. Id-free trees; server re-keys on insert.
4. Frozen 2-level `section → block` hierarchy.
5. Only the existing `insert-pattern` / `applyTemplate` commands mutate the document.
6. No new top-level registry types beyond the additive facade; no `Command` subclasses.

**Non-goals (this phase):**
- No implementation, no migrations, no runtime/serialization/BuilderState/CommandBus change.
- No new command for kit coalescing (deferred).
- No synced resources; no payment/marketplace/cloud implementation.

---

## 8. Open Questions / Future

1. **Kit undo granularity** (§5.3): one-undo-per-member vs a coalescing orchestration over
   existing commands. Leaning toward per-member for v1 (zero runtime risk).
2. **Block-kind placement** (§1.1): default target section when none is selected — reuse the
   insert path's current target resolution vs a dedicated wrapper. To be confirmed against
   the live insert behavior at implementation time.
3. **Atomic vs draft resources:** whether user-saved in-progress resources appear in the
   library as a distinct `local`/draft state, or only on explicit save. Defer to a later UX
   phase.
4. **Kit ↔ Starter Pack convergence:** whether 26.28 Starter Packs are re-projected as
   `kind: 'kit'` (references-only kits) in the facade immediately, or only once tokens are
   added. Recommended: project them as kits now (additive), add token payloads later.

---

*End of design-resource-model.md*
