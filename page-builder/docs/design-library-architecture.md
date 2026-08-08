# Design Library Architecture (Phase 26.40A)

> **Type:** Product Architecture. **No production code, migrations, runtime, serialization,
> BuilderState, CommandBus, or Extension-API changes.** This is the master architecture
> document; it sits on top of the *frozen* runtime and the Phase 26.40 Extension API.
>
> **Companion documents**
> - [product-decisions.md](product-decisions.md) — audit, competitor research, 3 candidates, final decision
> - [design-resource-model.md](design-resource-model.md) — the Design Resource taxonomy
> - [design-preview-architecture.md](design-preview-architecture.md) — runtime preview
> - [design-package-specification.md](design-package-specification.md) — Package v2 (`tncms.design-package`)
> - [licensing-boundary.md](licensing-boundary.md) — the entitlement boundary

---

## 1. Purpose & Scope

The **Design Library** is the single surface through which every reusable design artifact
enters a page: **Blocks**, **Sections**, **Full Page Templates**, and (future) **Starter
Kits** — sourced from the product itself, the user's own saved work, community packs,
premium packs, and (future) a marketplace and cloud library.

One architecture powers both editions. **FREE and PRO share** the runtime, renderer,
importer, serializer, **package format**, preview engine, and design resources. **Only
licensing differs** (see [licensing-boundary.md](licensing-boundary.md)).

This document specifies:
- the **audit** of what exists today (§2),
- the **chosen architecture** and its layering (§3),
- the **Design Library information architecture** (§4),
- how resources reach the **frozen runtime** (§5),
- **marketplace readiness** (§6), and
- **future validation** against AI generation, cloud sync, auto-updates, versioning,
  and dependency management (§7).

The three core seams — one IA, one package, one entitlement boundary — are each
finalized in a companion spec, linked above.

---

## 2. Audit of the Current Architecture (Ground Truth)

All paths below are real, under
`C:\laragon\www\laravel-cms\plugins\page-builder\`. Strengths/weaknesses analysis is in
[product-decisions.md §1](product-decisions.md); this section is the factual inventory the
architecture must respect.

### 2.1 Frozen runtime & document model

Editor types — `resources/editor/types.ts`:

```ts
interface BlockNode   { type: string; id?: string; data: Record<string, unknown> }
interface SectionNode { type: string; id?: string; data: Record<string, unknown>; blocks: BlockNode[] }
interface LayoutPayload { schema?: string; sections: SectionNode[] }
```

- **Strictly 2-level:** section → block. Blocks are leaves.
- `id` is optional client-side; the **server assigns/re-keys ids** on insert.
- **State machine (Frozen-Internal):** `src/State/BuilderState.php`, `src/State/CommandBus.php`.
  One command = one undo entry. Selection-only commands don't snapshot/dirty.
- **The single mutation path for inserting design content** is the existing
  **`insert-pattern`** command over an **id-free tree**. Template apply reuses it:
  `applyTemplateFromSource → applyTemplate → insertPattern` (atomic; throws *before* the
  command if resolution fails; exactly one undo entry).

> **Hard constraint:** the Design Library adds **no** new command, **no** new node field,
> **no** third hierarchy level, **no** serialization change. It projects and routes; the
> runtime mutates.

### 2.2 Component definitions, registries, rendering (PHP)

- Contracts: `src/Contracts/ComponentDefinition.php`, `BlockDefinition.php`,
  `SectionDefinition.php`, `ComponentInstance.php` (+ `Abstract*` bases).
- `ComponentInstance` lifecycle: `resolve()` → `renderView()`/`renderData()`, with 4
  overridable hooks (`beforeResolve/afterResolve/beforeRender/afterRender`).
- Registries: `AbstractRegistry` + `BlockRegistry`/`SectionRegistry` (**overwrite /
  last-write-wins**). Pattern/preview/token/preset registries with their own dup policies.
- Facade: `src/Facades/PageBuilder.php` — `registerSection/registerBlock`, `sections()`,
  `blocks()`, `runtime()`, `tokenRegistry()`, `columnLayoutPresets()`, `render()`.
- Hooks: `RuntimeHookManager` — **4 hook points only** (Public).

### 2.3 Pattern Library (26.11 + server 25)

- Editor: `patterns/library/patternDefinition.ts` — `PatternDefinition {id, name,
  category?, description?, tree}`; **id-free** `PatternTree`; in-memory
  `PatternLibraryRegistry`. Inserted via `insertPattern`.
- Server: `PatternManifest` (REST `/api/page-builder/patterns`) — rich metadata,
  variables, preview, premium flag, packs.
- **Section-level** reusable artifacts.

### 2.4 Template Library (26.21) & source binding (26.24)

- `templates/library/TemplateLibraryItem.ts`:

```ts
interface TemplateLibraryItem {
  id: string          // `<source>:<templateId>`
  templateId: string
  name: string
  description?: string
  category: string    // invariant KEY
  thumbnail?: string  // URL only
  previewUrl?: string // URL only (26.36)
  tags?: string[]     // invariant KEYS
  source: string      // invariant KEY
}
```

- Providers: Base / Static / Builtin / User / Imported / **Composite** (`templateLibrary`).
- Sources — **active:** `builtin`, `user`, `imported`; **reserved:** `marketplace`,
  `remote`, `cloud`.
- Definition binding: `TemplateDefinitionSource {source, getDefinition(id)}` +
  `TemplateSourceResolver` (dispatch by source key; unknown → `unsupported_template_source`).
- `TemplateDefinition {id, name, category?, description?, tree}`.

### 2.5 Package I/O (26.26) & compatibility (26.27)

- `tncms.template-package` **v1**: `TemplatePackage {schema, version:'1.0', metadata{id,
  name, description?, author?}, templates: TemplateDefinition[], compatibility?}`.
- Import: **validation-first, atomic**, lands in `ImportedTemplateDefinitionSource` +
  publishes to `importedTemplateLibrary`. Normalization strips tree ids, JSON-safe,
  **preserves asset URLs**.
- Compatibility: `PackageCompatibility {builder?(semver range), requiredComponents?,
  requiredFeatures?}`; `versionRange` semver; `nullMigrationProvider` (migration seam,
  contract-only); `BUILDER_VERSION='1.0.0'`.
- **Gap:** v1 carries **templates only** — no assets/fonts/icons/localization/preview.

### 2.6 Distribution (26.33), Starter Packs (26.28–32), Insert (26.37–38), Preview (26.36), Assets (26.20)

- Distribution: `DISTRIBUTION_SOURCE` active `builtin`/`local`(→user)/`imported`;
  reserved `marketplace`/`remote`/`cloud`. `TemplateDistributionRegistry` throws-on-dup.
- Starter Packs: `TemplateStarterPack {id, name, version, category, templates: string[]
  (references), thumbnail?, source?, compatibility?}`; `InstalledPack` references-only;
  adoption discovery joins ledger → library. Registries **freeze-after-boot**.
- Insert: `InsertProvider {key, label, open()}` — a **router** to blocks/patterns/
  templates/assets pickers. It never resolves or inserts.
- Preview: **static image/URL only** — `TemplatePreview {thumbnail?, previewUrl?}`,
  sanitized. **No runtime render today.**
- Assets: `AssetReference {kind:'media'|'external', src(URL), alt?, id?(display-only)}`.
  **URL-identity**; media id **never serialized**; `isSafeAssetUrl` (http/https/relative).

### 2.7 Extension API freeze (26.40)

Tiers: **Public** (SemVer-protected) / **Internal** / **Experimental** (reserved) /
**Frozen-Internal** (not exposed). Public seams the Design Library builds on:

- **TS:** `PatternProvider`, `TemplateProvider`, `TemplateDefinitionSource`,
  `InsertProviderRegistry` (throws-on-dup), `TemplateDistributionRegistry` (throws-on-dup),
  `registerFieldEditor`, `registerMediaPicker`, `TokenRegistry`, package/pack I/O.
- **PHP:** `PageBuilder` facade, `Abstract*Definition`, `Block/SectionRegistry`
  (overwrite), `RuntimeHookManager` (4 hooks), `PatternRegistry`,
  `PreviewProviderRegistry`, `TokenRegistry`, `ColumnLayoutPresetRegistry`.
- **Frozen-Internal (off-limits):** `CommandBus`, `BuilderState`, serialization, Command
  subclasses, undo/redo mechanics.

### 2.8 Existing docs (no filename collisions)

`page-builder-extension-api.md`, `page-builder-registry-specification.md`,
`page-builder-versioning-and-deprecation-policy.md`, `builder-concepts.md`,
`template-library-pack-discovery.md`, `template-package-foundation.md`,
`template-starter-packs.md`, `audits/phase-26.40-*`. The six new deliverable filenames do
**not** collide.

---

## 3. Chosen Architecture — "Design Library facade + Design Resource model"

Decision and alternatives are in [product-decisions.md §3–§4](product-decisions.md). In
short: **Candidate B**, absorbing Candidate C's Kit as an *optional first-class kind*.

### 3.1 One-paragraph statement

Introduce a **Design Resource** — a metadata envelope with a `kind ∈ {block, section,
page, kit}` that *points at* an existing backend provider (pattern provider / template
source / kit) rather than being new storage. Present all resources through a **Design
Library facade** (a composite read-model over the existing Template Library, Pattern
Library, and future Kits). Transport them with **one Package v2** format. Show them with a
**runtime preview** that renders through the real pipeline. Gate them with **one
EntitlementResolver** that *badges* at discovery/preview and *enforces* at
import/install/update. Nothing existing is merged or rewritten; the frozen runtime and the
26.40 API are untouched.

### 3.2 Layer diagram

```
┌──────────────────────────────────────────────────────────────────────────┐
│  DESIGN LIBRARY (new facade / read-model — additive)                       │
│  • IA: kinds, categories, tags, search, filters, favorites, installed      │
│  • Aggregates providers into a uniform DesignResourceItem                  │
│  • Consults EntitlementResolver for BADGING (never hides, never dupes)     │
└───────────────┬───────────────────────────┬──────────────────────────────┘
                │ projects                   │ badges
   ┌────────────┴───────────┐   ┌────────────┴───────────────┐
   │ EXISTING BACKENDS      │   │ CROSS-CUTTING (new seams)   │
   │ (reused, not merged)   │   │ • EntitlementResolver       │
   │ • Pattern Library      │   │ • DesignPreview engine      │
   │ • Template Library 26.21│   │ • Package v2 import/export  │
   │ • Template Source 26.24│   └────────────┬───────────────┘
   │ • Starter/Kit packs    │                │ enforce (import/install/update)
   │ • Distribution 26.33   │                │
   └────────────┬───────────┘                │
                │ resolve tree / apply       │
┌───────────────┴────────────────────────────┴──────────────────────────────┐
│  FROZEN RUNTIME (unchanged)                                                 │
│  applyTemplate → insertPattern → CommandBus → BuilderState (1 cmd = 1 undo) │
│  LayoutPayload → SectionNode → BlockNode (2-level). Server re-keys ids.     │
└────────────────────────────────────────────────────────────────────────────┘
```

### 3.3 What is new (design-only) vs reused

| New (contract/spec only, no code this phase) | Reuses (unchanged) |
|---|---|
| `DesignResource`/`DesignResourceItem` envelope + `kind` | Pattern Library, editor `PatternDefinition`, server `PatternManifest` |
| Design Library facade (composite read-model) | Template Library 26.21 (`TemplateLibraryItem`, providers) |
| `DesignPreview` + runtime preview engine | Template Source 26.24 + `applyTemplate`/`insertPattern` |
| `tncms.design-package` v2 (superset of v1) | Package v1 import (validation-first, atomic) |
| `EntitlementResolver` + entitlement tag | Compatibility 26.27 (`PackageCompatibility`, versionRange, migration seam) |
| Kit as first-class `kind` | Starter Packs 26.28–32, Distribution 26.33, reserved sources |

### 3.4 Design invariants

1. **Additive.** No merge, no rewrite; the facade is pure projection.
2. **Frozen runtime respected.** Only `insert-pattern` mutates; gates run *before* it.
3. **Copy-on-insert.** Id-free trees re-keyed on insert; **no synced/live-linked**
   resources.
4. **URL-only assets.** No storage/attachment ids ever serialized.
5. **Invariant KEYS.** `kind`, `source`, `category`, `tag` keys are machine-invariant;
   only display strings localize.
6. **One of everything.** One library, one package format, one preview engine, one
   entitlement boundary — FREE and PRO differ only by entitlement.

---

## 4. Design Library — Information Architecture

A single library surface (replacing the need to know "pattern vs template") that the
Insert router (26.37) can point *one* entry at.

### 4.1 The uniform item

Every backend projects into a `DesignResourceItem` (full shape in
[design-resource-model.md](design-resource-model.md)):

```ts
interface DesignResourceItem {
  id: string           // `<source>:<kind>:<resourceId>`
  kind: 'block' | 'section' | 'page' | 'kit'
  source: string       // builtin | user | imported | community | premium | marketplace | remote | cloud
  name: string
  description?: string
  category: string     // invariant KEY
  tags?: string[]      // invariant KEYS
  thumbnail?: string   // URL only
  previewUrl?: string  // URL only
  entitlement?: 'free' | 'pro' | 'premium'   // classification (badge), not a gate
  compatibility?: PackageCompatibility
  provenance?: { installedFromPack?: string }
}
```

### 4.2 Navigation model

Primary axes users browse by:

| Axis | Values | Backed by |
|---|---|---|
| **Kind** | Blocks · Sections · Full Page Templates · Starter Kits (future) | `kind` discriminator |
| **Collection** | Installed · Favorites · Local (my saved) · Community · Premium · Cloud (future) | source + provenance + local favorites store |
| **Category** | invariant category KEYS (hero, cta, pricing, …) | existing category KEYS |
| **Tags** | invariant tag KEYS | existing tags |
| **Entitlement** | Free · Pro · Premium | `EntitlementResolver` badge |
| **Search** | free-text over name/description/tags | existing filters (`searchLibrary`, pattern filters) |

- **Blocks · Sections · Full Page Templates · Starter Kits · Favorites · Installed ·
  Community · Premium · Local · Cloud (future)** are all facets of the *same* library, not
  separate apps.
- **Premium/pro items are always listed** (with an upgrade/lock **badge**) — never hidden,
  never duplicated. See [licensing-boundary.md §4](licensing-boundary.md).
- Categories, tags, search, and filters reuse the existing library/pattern filter
  utilities; the facade adds `kind` and `entitlement` as new facet dimensions only.

### 4.3 Composition (how the facade is built)

The facade is a **composite provider** analogous to the existing
`CompositeTemplateLibraryProvider`, but one level up: it aggregates
- the Template Library (pages + user-saved),
- the Pattern Library (blocks + sections),
- future Kit providers,
and projects each into `DesignResourceItem`. It holds **no storage** of its own and reads
snapshots live (newly installed packs surface immediately, as adoption discovery already
does).

### 4.4 Relationship to Insert (26.37–38)

Today Insert routes to four pickers. Under this architecture the **Design Library becomes
the single destination** for blocks/patterns/templates (assets remain their own media
picker). The Insert router is unchanged in contract — one provider's `open()` opens the
Design Library, optionally pre-filtered by `kind`.

---

## 5. From Resource to Runtime (the apply path)

Full detail in [design-resource-model.md §5](design-resource-model.md). Summary of how
each kind reaches the frozen runtime **without changing it**:

| Kind | Resolves to | Runtime path | Undo granularity |
|---|---|---|---|
| `block` | id-free tree (atomic component within a section) | `insertPattern` | 1 command = 1 undo |
| `section` | id-free `PatternTree` | `insertPattern` (existing pattern path) | 1 command = 1 undo |
| `page` (Full Page Template) | `TemplateDefinition.tree` | `applyTemplate → insertPattern` (existing 26.23 path) | 1 command = 1 undo |
| `kit` (future) | ordered member resources (+ tokens applied via `TokenRegistry`) | a **scripted sequence** of the above; each member still goes through `insert-pattern` | *Open question* — see below |

- **Full Page Templates** are production-ready Builder documents: **import once, then
  replace only your own content.** Placeholder/sample content is copied in and becomes the
  user's (copy-on-insert). No serialization change; the tree is id-free and server-re-keyed.
- **Kits** apply tokens through the Public `TokenRegistry` and insert members through the
  same path. Whether a kit apply is one coalesced undo or one-per-member is an **open
  question** (see [design-resource-model.md](design-resource-model.md)); either way it uses
  only existing commands.

> No kind introduces a new command, a new node field, or a third hierarchy level.

---

## 6. Marketplace Readiness

The architecture is marketplace-*ready* without building a marketplace this phase.

| Requirement | How it is already satisfied / anticipated |
|---|---|
| **Community packs** | `community` source (maps onto reserved `remote`); Package v2; advisory signature; free entitlement |
| **Official packs** | `builtin`/official source; signed; free or bundled entitlement |
| **Commercial sellers** | `marketplace` reserved source; Package v2 **signature + entitlement + dependencies**; `EntitlementResolver` gates import/install |
| **Offline packs** | imported via existing atomic importer; still resolver-checked at install |
| **Cloud packs** | `cloud` reserved source; future sync provider plugs in as another distribution provider |
| **Same format everywhere** | one `tncms.design-package` v2 for community/official/premium — only entitlement + trust policy differ |
| **Discovery** | Design Library facet by source + entitlement; premium always listed + badged |

No redesign is required to add a storefront later: it is a new distribution provider + an
`EntitlementResolver` data source. Payments are explicitly **out of scope**
([product-decisions.md §4.3](product-decisions.md)).

---

## 7. Future Validation

Confirming the architecture composes with anticipated futures **without redesign**:

| Future capability | Compatibility |
|---|---|
| **AI-generated pages** | AI output is just a `DesignResource` (an id-free tree) → enters via the *same* import/preview/insert path and *same* entitlement rules. No special case. |
| **Marketplace** | §6 — reserved sources + seller-ready Package v2 + resolver. |
| **Cloud sync** | Reserved `cloud` source; a cloud provider is another distribution provider behind the facade; nothing else changes. |
| **Auto-updates** | Package v2 `version` + `compatibility` + `dependencies`; updates re-run the validation-first/atomic importer and re-check entitlement (see [licensing-boundary.md](licensing-boundary.md)); installed content keeps working (grandfathering). |
| **Version compatibility** | Reuses 26.27 `PackageCompatibility` + `versionRange`; blocking `reasons` vs non-blocking `warnings` already modeled. |
| **Dependency management** | Package v2 `dependencies[]` (resource/pack references + version ranges); missing-known → block, unknown → warn — mirrors 26.27 gating. Kits depend on shared resources this way. |
| **Migration** | `nullMigrationProvider` (26.27) is the hook for a real v1→v2 migrator; v1 packages import as v2 `page`/`section` resources ([design-package-specification.md](design-package-specification.md)). |

---

## 8. Non-Goals (This Phase)

- No production code, migrations, or config changes.
- No change to runtime, serialization, BuilderState, CommandBus, or the 26.40 Extension API.
- No payment/storefront implementation; no cloud sync implementation.
- No signing/i18n infrastructure — signatures and localization are **seams** in Package v2.
- No synced/live-linked resources.

Everything above is a **contract/specification**. Implementation is a later, additive
phase that plugs into the Public seams enumerated in §2.7.

---

## 9. Document Map

| Concern | Document |
|---|---|
| Audit · competitors · candidates · decision | [product-decisions.md](product-decisions.md) |
| Resource taxonomy & kind→runtime mapping | [design-resource-model.md](design-resource-model.md) |
| Runtime preview | [design-preview-architecture.md](design-preview-architecture.md) |
| Package v2 format | [design-package-specification.md](design-package-specification.md) |
| Entitlement boundary | [licensing-boundary.md](licensing-boundary.md) |
| Master architecture (this doc) | design-library-architecture.md |

---

*End of design-library-architecture.md*
