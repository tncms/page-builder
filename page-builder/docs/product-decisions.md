# Product Decisions — Design Library (Phase 26.40A)

> **Type:** Product Architecture decision record. **No production code.**
> **Scope:** The Design Library that will power Page Builder FREE, Page Builder PRO,
> Community Design Packs, Premium Design Packs, and (future) Marketplace + Cloud Library.
> **Status:** Decided. Candidate B selected.
>
> Companion documents:
> - [design-library-architecture.md](design-library-architecture.md) — the master architecture
> - [design-resource-model.md](design-resource-model.md) — the resource taxonomy
> - [design-preview-architecture.md](design-preview-architecture.md) — runtime preview
> - [design-package-specification.md](design-package-specification.md) — Package v2
> - [licensing-boundary.md](licensing-boundary.md) — entitlement boundary

---

## 0. Executive Summary

The Page Builder has, over phases 26.11–26.40, grown two parallel reusable-design
concepts (**Patterns** and **Templates**), a metadata **Template Library**, a
portable **package format** (`tncms.template-package` v1), a **compatibility/versioning**
layer, a **distribution** abstraction with reserved `marketplace`/`remote`/`cloud`
source keys, a **starter-pack** + installation + adoption stack, a **unified Insert**
router, and a **static** (image-only) preview. Phase 26.40 then *froze* the public
Extension API and the internal runtime (CommandBus / BuilderState / serialization).

This is a strong foundation but it is **fragmented**: there is no single surface a
user browses to find "a thing to drop into my page," no single package that can carry
a whole design (assets, fonts, icons, localization, previews), no live preview, and no
notion of FREE vs PRO entitlement.

This phase decides the architecture that unifies these into **one Design Library** —
without changing the frozen runtime, serialization, or Extension API. After auditing
the current system, researching Elementor, Gutenberg, Framer, and Webflow, and
designing three candidate architectures, we select:

> **Candidate B — "Design Library facade + Design Resource model":** an additive
> aggregation layer that presents every reusable artifact (block, section, full page
> template, and future starter kit) as a uniform **Design Resource** behind one
> discovery IA, one **Package v2** format, one **runtime preview** engine, and one
> **entitlement boundary** — while the existing Pattern/Template/Starter-Pack
> subsystems remain intact underneath and the frozen runtime is untouched.

The rest of this document is the reasoning: the audit (§1), competitor research (§2),
the three candidates with trade-offs (§3), the decision and its justification (§4),
and a decision log + acceptance checklist (§5–§6).

---

## 1. Audit of the Current Architecture

Full factual inventory (file paths, type shapes) lives in
[design-library-architecture.md §2](design-library-architecture.md). This section
distills **strengths and weaknesses** only — no solutions yet.

### 1.1 What exists today

| Subsystem | Phase | Role |
|---|---|---|
| Frozen runtime | — | `LayoutPayload {schema?, sections}` → `SectionNode {type,id?,data,blocks}` → `BlockNode {type,id?,data}`. Strictly 2-level. CommandBus + BuilderState + serialization = Frozen-Internal. One command = one undo. |
| Pattern Library | 26.11 (+ server 25) | Editor `PatternDefinition {id,name,category?,description?,tree}` (id-free, in-memory) **and** server `PatternManifest` (REST, variables, preview, premium, packs). Section-level. Inserted via `insert-pattern`. |
| Template Library | 26.21 | Metadata discovery: `TemplateLibraryItem {id:'<source>:<templateId>', templateId, name, description?, category, thumbnail?, previewUrl?, tags?, source}`. Providers Base/Static/Builtin/User/Imported/Composite. |
| Template source binding | 26.24 | `TemplateDefinitionSource {source, getDefinition}`, `TemplateSourceResolver` dispatch by source key. |
| Apply runtime | 26.23 | `applyTemplateFromSource → applyTemplate → insertPattern` — one command, one undo, atomic (throws before command on failure). |
| Package I/O | 26.26 | `tncms.template-package` v1 `{schema, version, metadata, templates[], compatibility?}`. Validation-first, atomic. Strips ids, JSON-safe, preserves asset URLs. |
| Compatibility | 26.27 | `PackageCompatibility {builder?, requiredComponents?, requiredFeatures?}`, semver `versionRange`, `nullMigrationProvider` (migration seam, contract-only). |
| Distribution | 26.33 | `DISTRIBUTION_SOURCE` active `builtin/local/imported`; reserved `marketplace/remote/cloud`. Registry throws-on-dup. |
| Starter packs | 26.28–26.32 | `TemplateStarterPack` (references, not defs), install ledger (`InstalledPack`), adoption discovery. Freeze-after-boot. |
| Unified Insert | 26.37–26.38 | `InsertProvider {key,label,open()}` router → blocks/patterns/templates/assets pickers. Never inserts itself. |
| Preview | 26.36 | **Static image/URL only.** `TemplatePreview {thumbnail?, previewUrl?}`, sanitized. No runtime render. |
| Assets | 26.20 | `AssetReference {kind,src,alt?,id?}`. URL-identity; media id never serialized; `isSafeAssetUrl` (http/https/relative only). |
| Extension API | 26.40 | Public / Internal / Experimental / Frozen-Internal tiers. |

### 1.2 Strengths

1. **A frozen, well-understood runtime.** The 2-level tree + single `insert-pattern`
   mutation + one-command-one-undo is simple and battle-tested. Any Design Library
   *must* and *can* sit on top of it unchanged.
2. **Portability already enforced.** Templates carry **no** storage ids — only URLs.
   Trees are **id-free** and re-keyed on insert. This is exactly the invariant a
   distributable package format needs.
3. **Distribution + source abstraction already reserves the future.** `marketplace`,
   `remote`, `cloud` source keys exist in both `TemplateSource` and
   `DistributionSource`. Marketplace-readiness is largely a matter of *filling in*
   reserved slots, not redesigning.
4. **Validation-first, atomic import.** The importer already validates the whole
   package before registering anything and rolls back on any issue — the correct
   spine for a richer package format.
5. **Compatibility + migration seam already present.** `PackageCompatibility` +
   `versionRange` + `nullMigrationProvider` mean versioning/upgrade is a known,
   half-built concern rather than a greenfield risk.
6. **A clean extension boundary (26.40).** We know *exactly* what is safe to build on
   (Public seams) and what is off-limits (Frozen-Internal).

### 1.3 Weaknesses

1. **Fragmentation / no single surface.** A user must know whether the thing they want
   is a "pattern" or a "template," and go to a different picker for each. The unified
   Insert router (26.37) points at *four* different experiences rather than one library.
2. **Two overlapping resource concepts.** Patterns (section-level, id-free, sometimes
   variable-driven) vs Templates (page/multi-section, source-bound, packageable) have
   *real* behavioral differences but *blurry* conceptual boundaries. There is no
   umbrella taxonomy.
3. **Package format is templates-only.** v1 cannot carry assets, fonts, icons,
   localization, or preview media. A "buy a whole design" pack is not expressible.
4. **Preview is static.** Users cannot see what a resource will actually look like in
   *their* page before committing. This is the single biggest UX gap vs competitors.
5. **No entitlement concept.** Everything is FREE. There is no FREE/PRO/premium notion,
   no place tier logic belongs, and no guardrail preventing a future implementation
   from *duplicating* resources per tier (the classic anti-pattern).
6. **Starter Packs are shallow.** They reference templates only — they cannot bundle
   tokens/fonts/assets, so they can't express a cohesive "design system in a box."

### 1.4 Audit conclusion

The foundation is sound and deliberately extensible; the gaps are all at the **product
surface** (one library, one package, live preview, entitlement) — *not* in the runtime.
This strongly biases the decision toward an **additive layer**, not a rewrite.

---

## 2. Competitor Research

We studied four mature systems. For each: UX, resource model, package model, preview
model, licensing/commercial model — then what fits TN CMS and what to reject.

### 2.1 Elementor — Library, Templates & Kits

- **UX:** One "Library" modal opened from the canvas. Tabs for **Blocks** (section-level),
  **Pages** (full-page templates), and **My Templates** (user-saved). Premium items are
  listed inline with a **PRO lock badge**; you can *preview* them but inserting requires
  PRO. "Insert" drops the template into the current page; you then edit content.
- **Resource model:** Blocks → Pages → **Kits**. A **Kit** is the site-level unit:
  templates + global colors/fonts (design tokens) + theme settings + sometimes content.
  Envato "Template Kits" are the commercial packaging of this.
- **Package model:** Export/import as a zip with a manifest, definitions, and (for kits)
  global styles + a dependency/plugin manifest. Kits are the "whole look" transport unit.
- **Preview model:** **Live rendered preview** in an iframe — the actual template renders,
  responsive. This is the gold standard users expect.
- **Licensing/commercial:** FREE core + PRO subscription unlocks specific templates/blocks/
  widgets. Same library, same resources, **badge + gate** — resources are not duplicated
  per tier. Elementor Cloud adds cross-site saved libraries.
- **Fits TN CMS:** the Block→Section→Page→Kit ladder maps almost 1:1 onto our
  Pattern→Section→Template→Starter-Pack; the **preview-but-lock** licensing model; the
  **kit = tokens + resources** packaging; live iframe preview.
- **Reject / defer:** Elementor Cloud lock-in; mandatory kit ceremony for simple inserts;
  bundling *plugin* dependencies (out of scope for a CMS page builder).

### 2.2 Gutenberg (WordPress) — Patterns, Block Themes, theme.json

- **UX:** Patterns surfaced in the inserter with **live scaled render**. Pattern
  categories. A remote **Pattern Directory** (community, wordpress.org) feeds patterns
  into the same inserter — all free.
- **Resource model:** **Patterns** (block markup), **Template Parts** (header/footer),
  **Templates** (page structures), all shipped by **Block Themes** together with
  **theme.json** (the global design-token / global-styles system). **Synced patterns**
  (formerly "reusable blocks") stay live-linked: editing one updates every instance.
- **Package model:** Block themes ship patterns/templates/theme.json as theme files;
  no separate portable commercial package format in core.
- **Preview model:** Live block render in the inserter — truthful, responsive-aware.
- **Licensing/commercial:** No paid tier in core; commercialization happens via the
  third-party plugin/theme ecosystem.
- **Fits TN CMS:** remote community source feeding **one** library (our reserved
  `remote`/`community`); `theme.json` validates our **TokenRegistry** as the token
  system a Kit should carry; live render preview.
- **Reject / defer:** **Synced/live-linked patterns.** They conflict directly with our
  "import once, then own your content" model and would require runtime linkage we don't
  have (and don't want — it fights copy-on-insert + id re-keying). We stay **copy-on-insert**.

### 2.3 Framer — Marketplace

- **UX:** Marketplace of **Templates** (full sites), **Components**, and **Plugins**.
  Live hosted preview URLs. "Remix" to start from a template.
- **Resource model:** Components and full-site templates; strong creator/seller identity.
- **Package model:** Hosted; templates are remixable projects.
- **Preview model:** Live hosted preview (a real running page).
- **Licensing/commercial:** Full **creator economy** — paid templates, revenue share,
  seller onboarding, ratings.
- **Fits TN CMS:** validates that **marketplace-readiness** (sellers, paid packs,
  ratings) must be *anticipated* by the architecture; live preview expectation.
- **Reject / defer:** Building payments / revenue-share **now**. We keep
  `marketplace`/`remote`/`cloud` **reserved** and make the package format seller-ready
  (signatures, entitlement, dependencies) without building a storefront this phase.

### 2.4 Webflow — Templates & Libraries

- **UX:** **Templates** (full sites, paid, quality-reviewed) + **Libraries** (shareable
  component/style sets across projects) + an **Apps/Marketplace**. Live hosted preview.
- **Resource model:** Full-site templates; component **Libraries** for cross-project reuse.
- **Package model:** Hosted; Libraries share components + styles.
- **Preview model:** Live hosted preview.
- **Licensing/commercial:** Paid template marketplace with **designer revenue share** and
  a **quality-review** gate.
- **Fits TN CMS:** the idea that **official/curated** vs **community** packs need
  different trust/verification (→ our **signature** + trust policy in Package v2);
  cross-project component libraries (→ future Kits / `cloud`).
- **Reject / defer:** Cloud-sync-dependent cross-project Libraries (defer to `cloud`);
  a heavyweight review pipeline (a policy concern, not an architecture concern now).

### 2.5 Cross-cutting synthesis

| Theme | Consensus across competitors | TN CMS decision |
|---|---|---|
| **One library, many kinds** | Elementor/Gutenberg surface blocks + pages (+ kits) in one place | **Adopt** — one Design Library, `kind` discriminator |
| **Live preview** | All four render the real thing | **Adopt** — runtime preview through the real pipeline |
| **Tokens travel with design** | Elementor Kits + Gutenberg theme.json | **Adopt** — Kit resource + Package v2 carries tokens/fonts |
| **Badge + gate licensing** | Elementor PRO lock badge; resources not duplicated | **Adopt** — badge at discovery/preview, enforce at import/install |
| **Marketplace/creator economy** | Framer/Webflow | **Anticipate, don't build** — reserved sources + seller-ready package |
| **Synced/live-linked resources** | Gutenberg synced patterns | **Reject** — copy-on-insert only |
| **Cloud lock-in** | Elementor Cloud | **Reject/defer** — reserved `cloud` |
| **Bundled plugin deps** | Elementor kits | **Reject** — out of scope for CMS page builder |

---

## 3. Candidate Architectures

All three respect the frozen runtime, serialization, and the 26.40 Extension API.
They differ in *how* the unification is modeled.

### 3.1 Candidate A — "Unify under Template Library" (fold everything into Templates)

Treat every reusable artifact as a `TemplateDefinition` with a `kind` field. Fold
Patterns into Templates (a pattern = a `section`-kind template). Extend Template
Library / Package / Distribution to carry all kinds. One library because there is one
concept: the Template.

- **Information architecture:** single Template Library, filtered by `kind`.
- **Resource model:** `TemplateDefinition + kind`. Patterns cease to be a distinct concept.
- **Preview model:** extend `TemplatePreview` for all kinds.
- **Package model:** extend `tncms.template-package` with `kind` per entry.
- **Licensing:** entitlement tag on template entries.
- **Scalability:** good — one pipeline to evolve.
- **Compatibility:** **risky** — folding Patterns disturbs the editor `PatternDefinition`
  in-memory registry, the server `PatternManifest` variable system, and the
  `insertPattern` path that patterns and templates already *share but wrap differently*.
- **Trade-offs:**
  - ➕ Fewest concepts; one library, one package, one distribution to maintain.
  - ➕ Maximally reuses 26.21–26.33.
  - ➖ Overloads the word "Template" to mean everything; the taxonomy gets muddy exactly
     where users need clarity (block vs section vs page).
  - ➖ Requires touching the Pattern subsystem (variables, manifests) that currently has
     distinct, working behavior → violates "surgical changes," raises regression risk.
  - ➖ Server `PatternManifest` and editor `PatternDefinition` don't cleanly become
     "templates" — they'd need shimming, adding hidden complexity.

### 3.2 Candidate B — "Design Library facade + Design Resource model" (additive aggregation)

Introduce a **`DesignResource`** metadata envelope with `kind ∈ {block, section, page,
kit}` that *points at* an underlying provider (pattern provider / template source) — it
is **not** new storage and **not** a new runtime node type. Introduce a **Design Library**
as a new **read-model/facade** that aggregates the existing Template Library, Pattern
Library, and (future) Kits behind **one discovery IA**. Underlying subsystems stay
intact. Add **Package v2** (superset envelope, backward compatible with v1), a
**runtime preview** engine, and an **EntitlementResolver** consulted for *badging* at
the library/preview and *enforcement* at import/install/update.

- **Information architecture:** one Design Library; facets by `kind`, `source`, `category`,
  `tags`, `entitlement`, `favorites`, `installed`, `community`, `premium`, `local`,
  `cloud`(future).
- **Resource model:** `DesignResource {id, kind, source, name, category, tags, thumbnail?,
  previewUrl?, entitlement?, compatibility?, provenance?}` → resolves to an underlying
  Pattern/Template/Kit provider. See [design-resource-model.md](design-resource-model.md).
- **Preview model:** `DesignPreview` = static thumbnail (fast default + fallback) +
  optional **runtime render** through the real pipeline. See
  [design-preview-architecture.md](design-preview-architecture.md).
- **Package model:** `tncms.design-package` v2, superset of v1, carries all kinds +
  assets/fonts/icons/localization/preview + signatures/dependencies. See
  [design-package-specification.md](design-package-specification.md).
- **Licensing:** single `EntitlementResolver`; badge vs enforce boundary. See
  [licensing-boundary.md](licensing-boundary.md).
- **Scalability:** each subsystem evolves independently behind the facade; new kinds/
  sources plug in as new providers.
- **Compatibility:** **highest** — nothing existing is merged or rewritten; the facade
  is pure projection; the runtime and 26.40 API are untouched; v1 packages still import.
- **Trade-offs:**
  - ➕ Additive — respects the frozen runtime, the extension freeze, and the *real*
     Pattern/Template behavioral split.
  - ➕ Unifies IA + package + licensing in exactly the three seams the phase asks for.
  - ➕ 1:1 with the proven Elementor "one library / many kinds / preview / import-once" UX.
  - ➕ Marketplace-ready via reserved sources + seller-ready Package v2.
  - ➖ Adds **one** abstraction layer (`DesignResource` / Design Library) that
     conceptually overlaps `TemplateLibraryItem` (26.21) — must be documented with a
     crisp mapping table so the overlap is intentional, not accidental.
  - ➖ Slightly more indirection than A for the pure "everything is a template" mental model.

### 3.3 Candidate C — "Kit-first / Design-System-first" (top-down)

Make the **Kit** (a design system: tokens + fonts + assets + member resources) the
**primary** unit, like Elementor Kits / Gutenberg theme.json. Blocks/sections/pages are
*members* of a kit. The Library is primarily a browser of kits; individual resources
are secondary.

- **Information architecture:** Library of Kits; drill into a kit to reach its resources.
- **Resource model:** Kit-owns-everything; tokens travel with resources by construction.
- **Preview model:** kit-level and member-level runtime preview.
- **Package model:** package = kit (tokens + fonts + assets + members) as the norm.
- **Licensing:** entitlement at the kit level (buy the whole look).
- **Scalability:** excellent for premium "whole-site" packs and design-system coherence.
- **Compatibility:** larger departure from today's bottom-up model; more to build before
  the *common* case (drop in one section) delivers value.
- **Trade-offs:**
  - ➕ Strongest design-system coherence (tokens + resources always together).
  - ➕ Best fit for premium "buy a whole site look" commerce.
  - ➖ Heavyweight for the dominant use case — a user who just wants one section is forced
     through kit ceremony.
  - ➖ Penalizes simple **community single-pattern** contributions (Gutenberg's most
     successful pattern is a *single* section, not a kit).
  - ➖ Biggest divergence from the proven bottom-up foundation; highest build cost to
     first value.

### 3.4 Side-by-side

| Dimension | A: Fold into Templates | **B: Facade + Resource** | C: Kit-first |
|---|---|---|---|
| Respects frozen runtime | ✅ | ✅ | ✅ |
| Touches Pattern subsystem | ⚠️ yes (risk) | ✅ no | ⚠️ restructures |
| One library / many kinds | ✅ | ✅ | ✅ (kit-centric) |
| Taxonomy clarity (block/section/page) | ➖ overloaded | ✅ explicit | ➖ subordinate to kit |
| One package format | ✅ | ✅ | ✅ |
| Live preview | ✅ | ✅ | ✅ |
| Licensing boundary clean | ✅ | ✅ | ✅ (kit-grained) |
| Marketplace-ready | ✅ | ✅ | ✅ |
| Common case (drop one section) | ✅ | ✅ | ➖ heavy |
| Build cost to first value | Medium (risky) | **Low (additive)** | High |
| Regression risk | Medium–High | **Low** | Medium |

---

## 4. Final Decision

### 4.1 Decision

> **Adopt Candidate B — "Design Library facade + Design Resource model."**

Selectively **absorb C's best idea**: the **Kit** is a *first-class resource kind*
within B (a `kind: 'kit'` that groups resources + carries tokens/fonts/assets via
Package v2), but Kits are **optional**, not mandatory. This gives us design-system
coherence for premium "whole-look" packs *without* imposing kit ceremony on the common
single-section case.

Explicitly **reject A** (folding Patterns into Templates disturbs a working subsystem,
overloads "Template," and violates surgical-change discipline) and **reject C as the
primary model** (kit-first is too heavyweight for drop-in-one-section and diverges most
from the proven foundation).

### 4.2 Justification

1. **It honors every freeze.** The runtime, serialization, CommandBus/BuilderState, and
   the 26.40 Extension API are untouched. The Design Library is pure projection over
   existing Public seams (Pattern/Template providers, distribution registries, preview
   registry, token registry).
2. **It matches the winning UX.** Elementor's "one library, many kinds, live preview,
   import-once, badge+gate" is the model users already expect — Candidate B reproduces
   it exactly while keeping our copy-on-insert semantics.
3. **It is the lowest-risk path.** Additive aggregation means no merges, no rewrites, no
   Pattern-variable regressions, and v1 packages keep working.
4. **It unifies precisely the three seams the phase demands.** One IA, one Package v2,
   one entitlement boundary — each documented in its own companion spec.
5. **It is marketplace-ready without building a marketplace.** Reserved
   `marketplace`/`remote`/`cloud` sources + seller-ready Package v2 (signatures,
   dependencies, entitlement, localization) mean community/official/premium/offline/cloud
   packs are all the same format, resolved by policy — no future redesign.
6. **It scales down and up.** A single community section is a one-resource contribution;
   a premium whole-site look is a `kit`. Same library, same package, same preview, same
   entitlement rules.

### 4.3 What we explicitly reject (industry features)

- **Synced / live-linked resources (Gutenberg).** Conflicts with import-once-then-own and
  id re-keying. We remain **copy-on-insert**.
- **Building payments / revenue-share now (Framer/Webflow).** The package format is
  seller-ready; the storefront is reserved, not built.
- **Cloud-sync lock-in (Elementor Cloud).** Reserved `cloud` source; not this phase.
- **Bundled plugin/theme dependencies (Elementor kits).** Out of scope for a CMS page
  builder; Package v2 dependencies are *resource/pack* references only.

### 4.4 Consequences

- **New (design-only) concepts introduced:** `DesignResource`/`DesignResourceItem`, the
  Design Library facade, `DesignPreview`, `tncms.design-package` v2, `EntitlementResolver`.
  All are **contracts/specifications** in this phase — no implementation.
- **Existing concepts preserved:** Patterns, Templates, Starter Packs, Insert router,
  Package v1, Distribution, Compatibility — all continue unchanged and become *backends*
  behind the facade.
- **Kit** supersedes/extends the 26.28 Starter Pack conceptually (a Starter Pack is a
  reference-only kit-without-tokens; a Kit adds tokens/fonts/assets). Migration is a
  future, additive step.

---

## 5. Decision Log

| # | Decision | Rationale | Rejected alternative |
|---|---|---|---|
| D1 | Additive facade, not a merge/rewrite | Honors frozen runtime + 26.40; lowest regression risk | Candidate A fold; Candidate C restructure |
| D2 | `DesignResource` with `kind` discriminator (block/section/page/kit) | Explicit taxonomy users understand; maps to existing backends | Overloading "Template" (A) |
| D3 | Kit = optional first-class kind, not mandatory root | Design-system coherence without ceremony tax | Kit-first (C) |
| D4 | One Package v2 (`tncms.design-package`), superset + v1-compatible | One format for community/official/premium; no fork | Per-tier or per-kind formats |
| D5 | Runtime preview through the *real* pipeline, read-only, no commit | Truthful preview == result; matches competitors | Static-only (status quo); fake mock render |
| D6 | Entitlement = badge at discovery/preview, enforce at import/install/update | FREE/PRO consume identical resources; never duplicate | Hiding premium; duplicating resources per tier |
| D7 | Copy-on-insert only | Consistent with id re-keying + import-once-then-own | Synced/live-linked patterns |
| D8 | Marketplace/remote/cloud stay reserved; package is seller-ready | Anticipate commerce without building a storefront | Building payments now |
| D9 | Signatures + dependencies + localization in Package v2 as **seams** | Ready for trust/curation/i18n; contract-only now | Building signing/i18n infra now |

---

## 6. Acceptance Criteria — Coverage Checklist

| Criterion | Where satisfied |
|---|---|
| Current architecture audited | §1 (here) + [design-library-architecture.md §2](design-library-architecture.md) |
| Competitor research completed | §2 (here) |
| Three candidate architectures documented | §3 (here) |
| Trade-offs analysed | §3.1–§3.4 (here) |
| One final architecture selected | §4 (here) |
| Design Resource model finalized | [design-resource-model.md](design-resource-model.md) |
| Design Library IA finalized | [design-library-architecture.md §4](design-library-architecture.md) |
| Runtime Preview architecture finalized | [design-preview-architecture.md](design-preview-architecture.md) |
| Package architecture finalized | [design-package-specification.md](design-package-specification.md) |
| Licensing boundary finalized | [licensing-boundary.md](licensing-boundary.md) |
| Marketplace readiness validated | [design-library-architecture.md §6](design-library-architecture.md) + [licensing-boundary.md](licensing-boundary.md) |
| No production code written | ✅ documentation only |

---

*End of product-decisions.md*
