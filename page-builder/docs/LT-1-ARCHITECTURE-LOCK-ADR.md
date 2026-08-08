# ADR: LT-1 Layout Translation Architecture Lock

- **ADR ID:** LT-1-ADR-002
- **Status:** Accepted & **FROZEN** — the architectural authority for LT-2 through LT-10.
- **Scope:** Page Builder plugin only. Core Translation Platform v1.0 remains frozen.
- **Relationship:** Supersedes the interim LT-1 model; extends [`LT-0-SYNCHRONIZATION-ADR.md`](./LT-0-SYNCHRONIZATION-ADR.md) (LT-0-ADR-001).

The goal of LT-1 is **architecture lock**, not features. This ADR freezes the Layout Translation
domain model so later phases extend it only additively.

---

## 1. Immutable descriptor

The canonical object is **`LayoutTranslationDescriptor`** — a pure, `final`, fully-`readonly`,
constructor-validated value object. It has **no** setters, mutable state, persistence logic, service
references, Eloquent models, query builders, or workflow execution. Storage entities never appear on
it. It carries semantic state only: identity keys + status + capabilities + relationship validity.

**Frozen semantic contract.** The descriptor's semantic responsibility never changes after LT-1.
Future phases may extend it **only** through additive optional metadata or dedicated child value
objects — never by changing existing meaning or adding behaviour.

---

## 2. Identity model (locked)

Three value objects, each defining canonical representation, validation, equality, stability,
serialization, and a namespace. Identity never depends on a slug, title, table name, or a raw
presentation locale string.

| Key | Represents | Canonical form | Origin |
|---|---|---|---|
| `TranslationGroupKey` | the cross-locale group | `translation-group:{ownerType}:{ownerId}` | **projection of the Core owner (Content) identity** — locale-independent |
| `PageTranslationKey` | one locale variant | `page-translation:{ownerType}:{ownerId}:{locale}` | group + Core-normalized canonical locale |
| `LayoutKey` | the layout implementing a translation | `layout:{ownerType}:{ownerId}:{locale}` | derived from the `PageTranslationKey` |

**Core public-contract gap (documented, not simulated).** For core Pages/Posts (the parent +
`cms_content_translations` child model), Core exposes **no dedicated `@stable` typed translation-group
identity value object**. The cross-locale anchor is surfaced only as the raw Content `(type, id)`, the
Experimental `LocalizedEntityInterface` `(localizedType, localizedKey)`, or —
for the *other* model — `TranslatablePluginContent::translationGroup()`. Therefore `TranslationGroupKey`
**consumes and projects** the Core owner identity; the plugin does **not** invent a parallel group-id
space and does **not** fabricate identity from `page_id` as an authority of its own. It fails closed —
a key is only ever built from a resolved Core owner. **Recommended future Core proposal:** a unified,
`@stable` translation-group identity contract for core content; when it exists, `TranslationGroupKey`
adopts it additively.

---

## 3. Multi-dimensional state (locked)

State is **not** one flat enum. Three independent dimensions, composed in the immutable
`LayoutTranslationStatus` VO which enforces cross-dimension consistency at construction:

- `LayoutTranslationExistence`: `NOT_CREATED`, `EXISTS`, `INVALID_REFERENCE`.
- `LayoutTranslationLifecycle`: `DRAFT`, `PUBLISHED`, `ARCHIVED` (applies iff existence = `EXISTS`).
- `LayoutSynchronizationState`: `NOT_APPLICABLE`, `IN_SYNC`, `SOURCE_CHANGED`, `TARGET_CHANGED`,
  `DIVERGED`, `CONFLICTED` (`NOT_APPLICABLE` unless a layout exists).

**Consistency invariants (constructor-enforced):** lifecycle present ⟺ existence = `EXISTS`;
synchronization = `NOT_APPLICABLE` unless existence = `EXISTS`. A `null` lifecycle is **not** a
lifecycle value — it means "no lifecycle because no layout exists", which existence already states.

**`MISSING` removed.** In the flat model `MISSING` and `NOT_CREATED` were aliases; absence of a layout
is now exactly `Existence::NOT_CREATED`. "The translation itself is absent" is a different entity (the
page translation), not a layout-existence value.

**Transition policy (rules only; nothing is executed in LT-1):** each dimension transitions
independently — lifecycle: `Draft ⇄ Published → Archived → Draft`; synchronization:
`NotApplicable → InSync → {SourceChanged | TargetChanged} → {Diverged | InSync}`, `any → Conflicted →
InSync`. A transition never crosses dimensions.

LT-1 only ever *produces* `NOT_CREATED`, `EXISTS(Draft|Published)`; the rest are reserved for the
persistence/synchronization phases.

---

## 4. Capability boundary (locked)

`LayoutTranslationCapabilities` (`supportsClone/Sync/Diff/Review/Preview/Publish()`), resolved by
`LayoutTranslationCapabilityResolverInterface`, is **architectural metadata only**. It answers *"what
is this implementation capable of?"* — never *can this user execute?*, *is this layout eligible?*, or
*should this workflow run?*

These are four **independent** layers that later phases must not merge:

```
Capability   →   Eligibility   →   Authorization   →   Workflow
(what the        (is this          (may this actor    (perform the
 impl can do)     layout/state      do it?)            operation)
                  eligible?)
```

**Enforced structurally:** `LayoutTranslationCapabilityResolverInterface::capabilitiesFor()` takes
**no per-descriptor status** — capability cannot depend on a specific layout's state, so it can never
absorb Eligibility. The validator likewise does **not** compare capabilities against status (that check
was removed in the LT-1.5 review — it was Eligibility logic living in validation). LT-1 implements only
the Capability layer; its default resolver returns all-unsupported. Eligibility is a future layer
(LT-3+); Availability (the feature flag) is part of Eligibility, not Capability.

---

## 5. Responsibilities & ownership

**Core owns:** locale authority (`cms.language` via `LanguageConfigurationContract`), translation
groups and the translation relationship, translation lifecycle contracts, public registries, and
diagnostics conventions. **Plugin owns:** the descriptor, identity projection, state model, capability
model, resolver, validation, diagnostics — and later clone/sync/diff/UX. The plugin consumes Core; it
never duplicates locale, translation-group, or lifecycle authority.

**Reduced storage surface:** LT-1's only storage seam is `LayoutTranslationStateReaderInterface`
(returns a *status*, not rows). Persistent per-locale storage is **LT-2**; the resolver/validator/
diagnostics do not change when it lands.

---

## 6. Validation & diagnostics

**Structural invariants live in the value objects, not the validator.** Identity coherence
(group ↔ translation ↔ layout) and cross-dimension state consistency are enforced at VO construction
and cannot be violated — a stronger guarantee than runtime validation. Relationship validity is carried
as a typed `LayoutTranslationResolutionFault` (not a magic string) from resolver to validator.

`LayoutRelationshipValidator` therefore validates a resolved descriptor for **integrity concerns that
require context beyond the VOs** — deterministic, read-only: relationship validity (the fault),
existence integrity (`InvalidLayoutReference`), and orphaned layouts (owner existence via the owner
locator). It does not re-check VO-owned invariants and does not judge capability-vs-status (Eligibility).
`LayoutTranslationDiagnostics::report()` never throws and exposes descriptor, identity, existence,
lifecycle, synchronization, capability, and validation — with no persistence dependency.

---

## 7. Architecture freeze

The following are **FROZEN** at LT-1; future phases extend only additively (new optional metadata,
new child VOs, richer capability/state readers) and must not redesign them:

- `LayoutTranslationDescriptor` and its semantic contract;
- the identity contracts `TranslationGroupKey` / `PageTranslationKey` / `LayoutKey`;
- the relationship model `Group → Page Translation → Layout`;
- the state dimensions and their consistency invariants;
- the capability model and the Capability→Eligibility→Authorization→Workflow boundary.

**Additive-extension rules (how the freeze survives 10 years):**

- **State/fault enums are OPEN.** New `existence` / `lifecycle` / `synchronization` / fault cases may be
  added additively; **every consumer MUST have a default/fallback arm** and must not assume
  exhaustiveness. Adding a case is non-breaking by this rule.
- **Identity is additively discriminable.** If a future need requires more than one layout per page
  translation (multiple Builder engines within Page Builder, or per-device layout *documents*),
  `LayoutKey` gains an optional discriminator segment (e.g. `…:{locale}:{variant}`) whose canonical
  form omits the segment for the default variant — existing `layout:{type}:{id}:{locale}` values stay
  stable. Device/responsive that stays *within* one document (today's model) needs no identity change.
- **New capabilities are additive booleans** on `LayoutTranslationCapabilities` (e.g. AI translation),
  advertised by richer resolvers.
- **New semantic axes are child VOs.** Anything the three state dimensions don't cover (e.g. AI
  translation quality/provenance, review status) attaches as additive optional metadata / a dedicated
  child value object on the descriptor — never by mutating an existing dimension's meaning.
- **Generalization is an additive extraction.** The descriptor/identity/state/capability pattern is
  generic; only `LayoutKey`, the state reader and the owner locator are Page-Builder-specific. A future
  shared Translation-Descriptor package can extract the generic core without breaking this contract.

Any change outside these additive rules requires a new ADR.

---

## 9. LT-1.5 Golden Architecture Review (corrections applied)

An independent hostile review before LT-2 exposed and corrected three foundation weaknesses:

1. **Capability/Eligibility boundary violation (HIGH).** `capabilitiesFor()` took a per-layout status
   and the validator compared capabilities against status — both let Capability absorb Eligibility.
   Fixed: capability resolution is status-free (§4); the capability-vs-status check and its
   `CapabilityInconsistent` issue were removed.
2. **Misplaced structural validation (MEDIUM).** The validator re-checked identity coherence and state
   consistency already guaranteed by the VOs. Fixed: those invariants are VO-owned; the
   `IdentityMismatch` / `InconsistentState` issues were removed (§6).
3. **Magic-string resolver→validator coupling (MEDIUM).** Fixed: replaced by the typed
   `LayoutTranslationResolutionFault`.

All other stress tests (clone, baseline, diff, synchronization, Builder UX, AI translation, multiple
engines, theme override, multi-device, plugin ecosystem) resolved as **additively compatible** under
the rules above — no further LT-1 change required.

---

## 8. Constraints upheld

No Core modification, no schema migration, no runtime activation (services dormant, unwired), no UI
change. Feature flag `page-builder.translation.enabled` (env `PAGE_BUILDER_TRANSLATION`) defaults
false; rollback is config-only. LT-1 remains an additive, dormant, plugin-only foundation.
