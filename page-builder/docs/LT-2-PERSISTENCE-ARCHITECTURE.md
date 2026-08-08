# LT-2 — Persistence Architecture

**Status: IMPLEMENTED (LT-2). Additive, flag-gated, dormant. No frozen LT-1 contract changed.**

Persists the canonical layout-translation relationship and its multi-dimensional status behind an
additive **binding table**, strictly under [`LT-2-GUARDRAILS.md`](./LT-2-GUARDRAILS.md) and the frozen
[`LT-1-ARCHITECTURE-LOCK-ADR.md`](./LT-1-ARCHITECTURE-LOCK-ADR.md).

---

## Why Options A and B were rejected

Both previously-proposed options modelled the relationship as **Page → Locale Layout**, which is the
relationship the frozen architecture explicitly rejects:

- **Rejected A** — `locale` column on `pb_layouts`, `unique(owner_type, owner_id, locale)`. Makes
  `owner + locale` the layout identity and mutates the canonical document store.
- **Rejected B** — `pb_layout_translations` keyed primarily by `(owner_type, owner_id, locale)`. Same
  `owner + locale` identity, and risks becoming a second layout-document store.

The frozen canonical relationship is **Translation Group → Page Translation → Layout**. Identity is the
`PageTranslationKey` / `LayoutKey`, never `owner + locale`.

## Why the binding table was selected

```
TranslationGroupKey → PageTranslationKey → LayoutKey → pb_layouts.id
```

A dedicated `pb_layout_translation_bindings` table owns **only** the relationship and the persisted,
three-dimensional status. `pb_layouts` remains the canonical layout-document store, untouched. The
binding layer is not a second document store — it never holds a layout payload.

---

## Layers & dependency direction

```
Resolver / Diagnostics / Reconciler            (application)
        ↓ depends on
Persistence Contracts  (BindingReader/Repository, DocumentLocator, frozen StateReader seam)
        ↓ implemented by
Repository / Adapters  (EloquentBindingRepository, PersistentStateReader, DocumentLocator, Validator)
        ↓ uses
Database Model         (LayoutTranslationBinding — never crosses the boundary)
```

Frozen LT-1 domain value objects depend on **none** of these. Persistence is the lowest layer.

## Components (all `LayoutTranslation/Persistence/…`)

| Layer | Class | Role |
|---|---|---|
| Result (domain) | `Results/PersistedLayoutTranslationBinding` | immutable binding returned across the boundary |
| Result | `Results/BindingWriteResult` + `BindingWriteOutcome` | typed write outcomes (Created/AlreadyExists/Updated/Conflict/InvalidIdentity/InvalidReference/Failed) |
| Result | `Results/BindingReconciliationReport` | backfill tally |
| Result | `Results/BindingIntegrityReport` + `BindingIntegrityIssue` | integrity findings |
| Contract | `Contracts/LayoutTranslationBindingReaderInterface` / `…RepositoryInterface` | read/write seams (return domain contracts) |
| Contract | `Contracts/LayoutDocumentLocatorInterface` | pb_layouts existence probe |
| Repository | `Repositories/EloquentLayoutTranslationBindingRepository` | CRUD; hydrates domain contracts; corrupt state → invalid-reference |
| Adapter | `PersistentLayoutTranslationStateReader` | backs the frozen `LayoutTranslationStateReaderInterface` with bindings |
| Adapter | `EloquentLayoutDocumentLocator` | reads pb_layouts existence |
| Service | `LayoutTranslationBindingReconciler` | additive, fail-closed, idempotent backfill |
| Validator | `LayoutTranslationBindingValidator` | integrity beyond VO construction (separate from the frozen LT-1 validator) |
| Model | `Models/LayoutTranslationBinding` | Eloquent; used only inside the repository |
| Console | `Console/BackfillLayoutTranslationBindingsCommand` | explicit backfill (never auto-run) |

---

## Identity ownership — locale metadata vs identity

The repository persists the **canonical serialized values** of the frozen identity value objects
(`translation_group_key`, `page_translation_key`, `layout_key`). It rehydrates identity **only** through
the existing factories `forOwner` / `forGroup` / `forTranslation`, and fails closed if the rehydrated
canonical value does not match the stored value (identity is never reinvented from metadata). `locale`
is an **indexed relationship/coherence column, not identity**.

## State rehydration (no frozen-VO change)

Three dimensions are persisted independently (`existence_state`, nullable `lifecycle_state`,
`synchronization_state`). The repository rehydrates `LayoutTranslationStatus` by **branching on
existence** through the existing constructors (`notCreated()` / `invalidReference()` /
`exists(lifecycle, synchronization)`). No `fromArray()`/`fromDimensions()` factory was added to the
frozen VO. Impossible or unreadable combinations fail closed to an **invalid-reference** status — never
silently normalized.

## Capability boundary

The repository never derives capability from stored state; capability remains implementation-static
metadata resolved by the LT-1 capability resolver. Capability / Eligibility / Authorization / Workflow
remain separate.

## Resolver integration & flag behaviour

The frozen `LayoutTranslationStateReaderInterface` is bound flag-aware: **off → legacy pb_layouts reader
(byte-identical legacy behaviour); on → `PersistentLayoutTranslationStateReader`**. When enabled, a
persisted binding supplies status; an un-bound source falls back to the authoritative pb_layouts
document (so the source is correct before backfill); an un-bound secondary is `NotCreated`. The resolver
output contract is unchanged and never exposes storage models. `null` is never used as a lifecycle.

## Failure & recovery

- Corrupt/incoherent persisted data → `InvalidReference` status / null hydration (fail closed).
- Duplicate relationship → typed `AlreadyExists`/`Conflict`, never a fatal error.
- Missing referenced layout / orphaned owner → surfaced by `LayoutTranslationBindingValidator`,
  read-only (diagnostics never repair).
- Backfill leaves unresolved records reported, never fabricated; re-runnable (idempotent).

## Future additive LayoutKey variants

Uniqueness is on `page_translation_key` and `layout_key` — **never** on `(group, locale)` — so the
future additive `LayoutKey` variant segment (multi-engine / per-device documents, ADR §7) remains
possible without a schema redesign.
