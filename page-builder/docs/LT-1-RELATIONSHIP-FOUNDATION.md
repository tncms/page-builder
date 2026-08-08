# LT-1 — Translation Group & Locale Layout Relationship Foundation

**Status: IMPLEMENTED & ARCHITECTURE-LOCKED (LT-1). Dormant behind a config flag — disabled by default.**

Establishes and **freezes** the canonical Layout Translation domain model. The authoritative contract
is [`LT-1-ARCHITECTURE-LOCK-ADR.md`](./LT-1-ARCHITECTURE-LOCK-ADR.md) (LT-1-ADR-002); this document is
the practitioner overview.

Not in this phase: cloning, synchronization, diff, conflict detection, review workflow, translation
UX, AI, persistence, migration, Core changes, runtime/UI activation.

---

## Canonical model

```
Translation Group   (Core: the shared cms_contents anchor)
        │
        ▼
Page Translation    (Core: a (content_id, locale) translation)
        │
        ▼
Page Builder Layout (plugin: an implementation detail of the translated page)
```

A layout is **not owned by a Page**. The canonical resolved object is the immutable
**`LayoutTranslationDescriptor`** carrying three identity keys, a multi-dimensional status, capabilities,
and relationship validity. Storage entities never appear on it.

---

## Identity model (`src/LayoutTranslation/Identity/`)

| Key | Canonical form | Notes |
|---|---|---|
| `TranslationGroupKey` | `translation-group:{ownerType}:{ownerId}` | locale-independent; a **projection of the Core owner (Content) identity** |
| `PageTranslationKey` | `page-translation:{ownerType}:{ownerId}:{locale}` | group + Core-normalized canonical locale |
| `LayoutKey` | `layout:{ownerType}:{ownerId}:{locale}` | derived from the page translation it implements |

Each defines canonical representation, validation, equality, stability, serialization, and a namespace.
Identity never depends on a slug, title, table name, or raw presentation locale string.

**Core gap:** Core exposes no dedicated `@stable` translation-group identity VO for core pages/posts —
the anchor is the Content `(type, id)` / Experimental `LocalizedEntityInterface` / (for the other model)
`TranslatablePluginContent::translationGroup()`. The plugin **consumes/projects** the Core identity and
does not invent a parallel one; fail-closed. See the ADR §2.

---

## Multi-dimensional state (`src/LayoutTranslation/State/`)

Three **independent** dimensions, composed in the immutable `LayoutTranslationStatus` VO (constructor
enforces cross-dimension consistency):

- **Existence** — `NOT_CREATED`, `EXISTS`, `INVALID_REFERENCE`.
- **Lifecycle** — `DRAFT`, `PUBLISHED`, `ARCHIVED` (present iff `EXISTS`).
- **Synchronization** — `NOT_APPLICABLE`, `IN_SYNC`, `SOURCE_CHANGED`, `TARGET_CHANGED`, `DIVERGED`,
  `CONFLICTED` (`NOT_APPLICABLE` unless a layout exists).

`MISSING` was an alias of `NOT_CREATED` and is **removed**; absence is `Existence::NOT_CREATED`. LT-1
produces only `NOT_CREATED` and `EXISTS(Draft|Published)`; the rest are reserved for later phases.
Per-dimension transition rules are defined (not executed) in the enums and the ADR §3.

---

## Capability boundary

`LayoutTranslationCapabilities` (`supportsClone/Sync/Diff/Review/Preview/Publish()`) is **metadata
only** — it answers "what is this implementation capable of?", never eligibility, authorization, or
whether a workflow should run. Those are four independent layers
(`Capability → Eligibility → Authorization → Workflow`) that must never be merged (ADR §4). This is
enforced structurally: `capabilitiesFor()` takes **no per-layout status**, so capability cannot depend
on a specific layout's state (that would be Eligibility). LT-1's default resolver returns
all-unsupported.

---

## Resolver, validator, diagnostics

- **`LayoutTranslationResolver`** returns a descriptor (never a storage entity): deterministic,
  read-only, no clone/fallback/write/persist. Flag off ⇒ every locale resolves the source-locale
  descriptor over the legacy shared layout. Unsupported owner / disabled locale ⇒ unresolvable;
  ambiguous storage ⇒ `INVALID_REFERENCE` surfaced (never guessed).
- **`LayoutRelationshipValidator`** validates a resolved descriptor for **integrity concerns beyond the
  value objects** — relationship validity (a typed `LayoutTranslationResolutionFault`), existence
  integrity (`InvalidLayoutReference`), and orphaned layouts. Structural invariants (identity coherence,
  cross-dimension consistency) are enforced at VO construction, not re-checked here; capability-vs-status
  is Eligibility, not validation. Deterministic, read-only, fail-closed.
- **`LayoutTranslationDiagnostics::report()`** — never throws; exposes descriptor, identity, existence,
  lifecycle, synchronization, capabilities, and validation without persistence dependency.

The whole foundation is **dormant** — not wired into the frontend renderer or the editor.

---

## Feature flag & rollback

`config('page-builder.translation.enabled')` (env `PAGE_BUILDER_TRANSLATION`), **default `false`**.
The `translation.*` namespace is shared by all future Page Builder translation features. Rollback is
config-only; no schema, no migration, no runtime wiring.

---

## Files

`src/LayoutTranslation/`: `Identity/{TranslationGroupKey,PageTranslationKey,LayoutKey}`,
`State/{LayoutTranslationExistence,LayoutTranslationLifecycle,LayoutSynchronizationState,LayoutTranslationStatus}`,
`LayoutTranslationConfig`, `LayoutTranslationCapabilities`,
`Contracts/LayoutTranslationCapabilityResolverInterface` + `DefaultLayoutTranslationCapabilityResolver`,
`Contracts/LayoutTranslationStateReaderInterface` + `DefaultLayoutTranslationStateReader`,
`LayoutTranslationDescriptor`, `LayoutTranslationResolver`,
`Contracts/LayoutOwnerLocatorInterface` + `ContentLayoutOwnerLocator`, `RelationshipValidationIssue`,
`RelationshipValidationReport`, `LayoutRelationshipValidator`, `LayoutTranslationDiagnostics`.
Edited (additive): `config/page-builder.php`, `PageBuilderServiceProvider.php`. Tests:
`tests/Feature/PageBuilder/PageBuilderLayoutTranslationTest.php`. ADR:
[`LT-1-ARCHITECTURE-LOCK-ADR.md`](./LT-1-ARCHITECTURE-LOCK-ADR.md).

No Core file, schema, runtime path, or UI was modified.
