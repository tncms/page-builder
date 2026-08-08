# ADR: Layout Translation & Structural Synchronization

- **ADR ID:** LT-0-ADR-001
- **Status:** Accepted (evidence-based) — LT-0. Governs LT-1…LT-10.
- **Scope:** Page Builder plugin only. Core Translation Platform v1.0 remains frozen.
- **Supersedes:** none. **Amended by:** a future ADR only.

Evidence for every decision is in [`LT-0-CORE-ADOPTION-AUDIT.md`](./LT-0-CORE-ADOPTION-AUDIT.md),
[`LT-0-INTEGRATION-MATRIX.md`](./LT-0-INTEGRATION-MATRIX.md), and
[`LT-0-STORAGE-AND-IDENTITY-AUDIT.md`](./LT-0-STORAGE-AND-IDENTITY-AUDIT.md).

---

## Context

Page Builder must provide **Layout Translation** — a translated page begins from a structural **clone**
of the source layout, is **independently editable** per locale, and can later be **synchronized** from
the source without destroying target work. The audit proved: (1) Core owns locale and the
`(content_id, locale)` translation relationship, exposed via frozen read-ports; (2) `pb_layouts` is
locale-blind (one row per `Content`); (3) node `id` regeneration preserves the `data` bag; (4) the only
reusable immutable baseline primitive is `DesignFingerprint`; (5) `LayoutStore::saveDraft` (used by the
`FullPageImportService` transactional pattern) is the canonical mutation path; (6) no Core gap exists.

---

## Decisions

### D1 — Translation unit: the complete layout document
The translatable unit is the whole normalized layout payload (`{schema, sections[…]}`), **not** a
text-field translator. Field text localization already exists via `LocalizedValue`/`Localize::text()`;
Layout Translation adds the missing **structural** dimension.

### D2 — Relationship authority: Core `(content_id, locale)`
Per-locale layouts are keyed **congruently with the Core translation set**. The `Content` row
(`cms_contents.id`) is the translation-group anchor; a layout translation exists for a locale iff a
`ContentTranslation(content_id, locale)` context exists. Page Builder **reads** the locale set through
`LanguageConfigurationContract::enabledLocales()` and **never builds a second locale registry or a
translation-group table**.

### D3 — Locale authority: `cms.language` (read-only)
Active/editing locale is read via `PublicLocaleContextContract::current()` / `editingLocale()`. The
plugin **never sets, persists, or mutates** locale (the contract has no setter by design).

### D4 — Target creation: deep clone via the canonical pipeline
"Create Translation" performs a **deep structural clone** of the source layout using the established
`FullPageImportService::import()` shape: `DB::transaction { load source → normalize() →
NodeIdGenerator::assign() (fresh physical ids) → LayoutStore::saveDraft(target) → verify }`. The clone
copies the complete hierarchy, styling, responsive settings, media/dynamic references, and design
references. It resets physical `id`s, publication state, and timestamps; it preserves cross-locale
correspondence (D6). **No blank layout. No source mutation.**

### D5 — Target independence
After cloning, the target layout is **fully independently editable** — reorder, add, remove, restyle,
replace media, redesign. Translated layouts are **never** forced to remain structurally identical to
the source.

### D6 — Correspondence: reuse `id`, add plugin-owned origin metadata **inside `data`**
Two identities are maintained:
- **`node_id`** — physical identity within one document (existing; regenerated on clone).
- **origin correspondence** — a stable cross-locale id captured at clone time.

Because the normalizer strips unknown top-level node keys but preserves `data` verbatim, and because
both id-regenerators rewrite only `id`, the correspondence id is stored **inside the node's `data`
bag** (e.g. `data['_origin']`). Target-only nodes carry a null/own-marker origin and are flagged
**target-owned**. Target-owned nodes are never removed or overwritten by source synchronization.

### D7 — Baseline: content-addressed, plugin-owned (not Core revisions)
The source baseline `B` used for three-way comparison is an **immutable reference to the source's state
at the last successful sync**, recorded as a **content-addressed fingerprint** (reuse
`DesignFingerprint` = `sha256(canonicalJson(payload))`) plus the referenced payload/reference. Baseline
canonicalization **strips physical `id`s and correspondence metadata** so source and target hash
comparably. **Core `cms.revision` is explicitly rejected as the baseline store** because it prunes to
`max_per_locale` (default 5) and would silently delete a baseline a target diverges from over time.

### D8 — Synchronization: three-way structural comparison
Never replace the target document. Compare `B` (baseline), `S` (current source), `T` (current target).
Derive `B→S` (new source changes) and `B→T` (target customizations); classify each node/property as
`ADDED | REMOVED | MOVED | STRUCTURE_UPDATED | STYLE_UPDATED | SETTING_UPDATED | CONTENT_UPDATED |
UNCHANGED | CONFLICTED`. The diff engine (LT-5) is **read-only and deterministic**.

### D9 — Conflict default: fail closed, keep target
Ambiguous or conflicting changes default to **keep target** and require explicit review. Non-destructive
invariants: never auto-overwrite translated text; never auto-remove a target-modified or target-owned
node; never replace the full target JSON; never mutate the source while syncing a target.

### D10 — Execution: explicit plan preview before any mutation
Synchronization produces a reviewable **plan** (LT-6). Only explicitly accepted items are applied
(LT-7), transactionally, target-only, with an **append-only synchronization history** and baseline
advanced on success. Full-document replacement is **prohibited and tested**.

### D11 — Core change: none
No frozen Core file is modified. No new Core port is required.

---

## Core-gap decision

For each required capability, the audit found a Stable Core port or a plugin-owned additive path —
**no `unavailable` gaps, no Core proposal needed**:

| Capability | Classification | Resolution |
|---|---|---|
| Current/editing/enabled locale | Stable Core port | consume `PublicLocaleContextContract`, `LanguageConfigurationContract` |
| Page translation set | Stable (Core relationship) | read `Content` `(content_id, locale)` via public helpers; never internal adapters |
| Per-locale layout storage | plugin-owned | additive `(owner, locale)` binding (LT-2) |
| Node correspondence | plugin-owned | additive metadata in `data` (LT-4) |
| Source baseline | plugin-owned | content-addressed fingerprint + reference (LT-4) |
| Diff / plan / execution / history | plugin-owned | new plugin services (LT-5…7, 9) |
| Immutable content-field history | Stable Core port | optional `cms.revision` for editor history only — **not** the sync baseline |

> Had a genuine gap existed, the rule is: **do not patch Core** — document the smallest additive
> public-port proposal, justify why plugin-only implementation would duplicate frozen authority, and
> **stop that branch at the gate** pending separate approval. No such branch exists.

---

## Consequences

**Positive:** no Core risk; single-language layouts stay byte-identical; reuses the proven transactional
clone pattern and existing id/`data`/fingerprint mechanics; target locales are truly independent;
synchronization is non-destructive and reviewable.

**Costs / risks:** additive schema for the `(owner, locale)` binding (LT-2) and correspondence/baseline
(LT-4); frontend `bodyFor` must become locale-aware with an **explicit** fallback policy
(currently locale-blind); editor pages/URLs must thread a target locale (additive). All are
plugin-local and gated.

**Invariants introduced (to be enforced and tested from LT-2 onward):**
- INV-LT-01 The source layout is never mutated during target creation or synchronization.
- INV-LT-02 The full target document is never replaced; only planned, accepted items mutate it.
- INV-LT-03 Target-owned and target-modified nodes are never auto-removed or auto-overwritten.
- INV-LT-04 Translated text is never auto-overwritten.
- INV-LT-05 Every applied sync is reversible via history/baseline; a failed sync leaves no partial layout.
- INV-LT-06 Locale is read-only from Core; the plugin never creates a locale/translation-group registry.
- INV-LT-07 Default-locale single-language layouts remain byte-identical while the feature flag is off.

---

## Addendum — LT-1 Architecture Correction (Accepted)

Refines the decisions above with the canonical domain model adopted when LT-1 was reopened. It amends
wording; it does not reverse any decision.

- **A1 — Canonical object is a descriptor, not ownership.** A layout is **not owned by a Page**. It
  belongs to a **Page Translation**, which belongs to a **Translation Group** (Core). The canonical
  resolved object is the immutable **`LayoutTranslationDescriptor`** (relationship identity + business
  state + capabilities + validity), resolved throughout the plugin. This supersedes the interim
  `LayoutOwnership` model. Refines **D2**.
- **A2 — Relationship model.** `Translation Group → Page Translation → Layout`. The layout is an
  implementation detail of a translated page, **not** a locale-specific child of the original Page.
- **A3 — Resolver returns the descriptor.** The resolver resolves a descriptor, never a storage entity;
  storage stays behind the descriptor boundary. Refines **D4/D8** for the read path.
- **A4 — Multi-dimensional business state.** `null`/`none` are never used for lifecycle. State is
  split into three independent dimensions — existence (`NOT_CREATED`/`EXISTS`/`INVALID_REFERENCE`),
  lifecycle (`DRAFT`/`PUBLISHED`/`ARCHIVED`), synchronization (`NOT_APPLICABLE`/`IN_SYNC`/
  `SOURCE_CHANGED`/`TARGET_CHANGED`/`DIVERGED`/`CONFLICTED`) — composed in `LayoutTranslationStatus`
  with constructor-enforced cross-dimension consistency. `MISSING` was an alias of `NOT_CREATED` and is
  removed. Consumed by the UI, the synchronization engine (D8/D9), and diagnostics.
- **A8 — Explicit identity model.** Identity is carried by locked value objects
  `TranslationGroupKey` / `PageTranslationKey` / `LayoutKey`. `TranslationGroupKey` projects the Core
  owner (Content) identity; Core exposes no dedicated `@stable` translation-group identity VO for core
  content (documented gap) — the plugin consumes, never invents, and fails closed.
- **A9 — Capability boundary.** Capability is metadata only, independent of Eligibility, Authorization
  and Workflow; those four layers must never be merged.
- **A5 — Capability descriptor.** The descriptor advertises `supportsClone/Sync/Diff/Review/Preview/
  Publish()` via `LayoutTranslationCapabilityResolverInterface`; implementations advertise capabilities
  instead of callers branching on concrete classes. LT-1 default = all unsupported.
- **A6 — Minimal storage seam.** LT-1's only storage seam is `LayoutTranslationStateReaderInterface`
  (returns a state, not rows). Persistent per-locale storage remains **LT-2**; the resolver/validator/
  diagnostics do not change when it lands.
- **A7 — Feature-flag namespace.** `page-builder.translation.*` (env `PAGE_BUILDER_TRANSLATION`),
  shared by every future Page Builder translation feature, replacing `layout_translation.*`.

These refinements change no Core file, schema, runtime path, or UI; LT-1 remains a dormant,
flag-gated, additive foundation. The full, frozen contract is
[`LT-1-ARCHITECTURE-LOCK-ADR.md`](./LT-1-ARCHITECTURE-LOCK-ADR.md) (LT-1-ADR-002), the architectural
authority for LT-2 through LT-10.
