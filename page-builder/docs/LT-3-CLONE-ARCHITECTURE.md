# LT-3 — Clone Architecture

**Status: DESIGN + AUDIT. One blocking decision (target-document owner) is OPEN and must be confirmed
before the write path is implemented.** Governed by
[`LT-3-BINDING-LIFECYCLE-GOVERNANCE.md`](./LT-3-BINDING-LIFECYCLE-GOVERNANCE.md),
[`LT-3-TRANSACTION-BOUNDARY-GOVERNANCE.md`](./LT-3-TRANSACTION-BOUNDARY-GOVERNANCE.md), and the frozen
LT-1/LT-2 contracts.

---

## 1. Storage & payload audit

- **`pb_layouts`** holds **exactly one document per owner** — `unique(owner_type, owner_id)` — with
  `editor_mode`, `draft_payload`, `published_payload`, `draft_updated_by`, `published_by`.
- **`LayoutStore`** keys every read/write by `(owner_type, owner_id)` (`find/resolve/getDraftPayload/
  getPublishedPayload/saveDraft/publish`). There is **no create-by-id and no per-locale write path**.
- **Node identity (LT-0):** node `id` is `pb-<ulid>`, **optional** in stored payloads, **regenerated**
  by the existing in-editor duplicate/import paths, and **document-local**. There is **no cross-document
  origin correspondence** concept yet — that is an LT-4 concern (LT-0-ADR-001 D6).
- **`ContentTranslation`** (`cms_content_translations`) is the Core row for a page translation, keyed
  `unique(content_id, locale)` with its own `id`.

## 2. Source model & payload selection (design)

The source and target are Page Translations **in the same Translation Group** (§2) — i.e. the same
`Content`, different locale. The source's effective document is read via the source binding
(`LayoutKey → pb_layouts.id`) or, for the default locale with no binding yet, the legacy
`(page, content_id)` row.

**Source payload precedence (deterministic):** published payload (when a valid published document
exists) → else draft payload (when a valid draft exists) → else `SourcePayloadUnavailable`. "No
published payload" = `pb_layouts.published_payload IS NULL`; "no draft" = `draft_payload IS NULL`. A
"valid" document is a non-null array with an array `sections` key. Draft and published are **never
merged**; an empty/malformed payload is **never silently cloned**.

## 3. Clone semantics & structural identity (design)

The target draft receives the **selected source payload verbatim** (structure + content), so the target
begins as a source-language draft. **Node ids are preserved, not regenerated** — regenerating them would
destroy the natural source↔target node correspondence that LT-4 will formalize into `origin_node_id`;
LT-3 must not invent that correspondence model. The target receives **new layout-level identity** (new
`pb_layouts.id`, target `LayoutKey`) and never reuses the source layout id. Publication metadata
(`published_by`, publish timestamps, source updater identity) is **not** copied; `published_payload`
stays null. No text is translated (AI/translation is a later phase).

## 4. Initial status (design)

On success, persist the frozen three-dimensional status **`EXISTS + DRAFT + IN_SYNC`**
(`LayoutTranslationStatus::exists(Lifecycle::Draft, Synchronization::InSync)`). `IN_SYNC` records that
the target was created from the exact source snapshot at clone time; it does **not** track future source
changes (no baseline is persisted in LT-3 — that is LT-4).

## 5. Eligibility model (design)

A dedicated `CloneEligibilityEvaluator` (never inside capability/repository/descriptor) returns a typed
`CloneEligibilityResult` over a `CloneIneligibilityReason` enum. Eligible only when: feature enabled;
clone capability supported; source & target resolvable; different Page Translations; same Translation
Group; source layout exists; source payload readable/valid; target Page Translation exists; target has
no binding; target has no conflicting layout; identities canonical; no integrity fault. Capability →
Eligibility → Authorization → Workflow stay separate; authorization is consumed at the entry point
(callers must authorize before invoking the service).

## 6. Failure model (design)

Typed `LayoutTranslationCloneOutcome`: `Created`, `AlreadyCloned`, `FeatureDisabled`,
`CapabilityUnsupported`, `SourceNotFound`, `SourcePayloadUnavailable`, `TargetNotFound`,
`DifferentTranslationGroup`, `SameTranslation`, `TargetLayoutConflict`, `InvalidIdentity`,
`IntegrityFault`, `ConcurrentConflict`, `PersistenceFailure`. Infrastructure failures are normalized at
the service boundary (unique-race → `AlreadyCloned`/`ConcurrentConflict`; FK failure →
`IntegrityFault`; malformed payload → `SourcePayloadUnavailable`; unexpected → `PersistenceFailure`).
No raw exceptions, SQL, models or rows are exposed; `Throwable` is never caught-and-pretend-success.

## 7. Atomic write sequence (design)

`LayoutTranslationCloneService` owns one transaction (`TransactionRunnerInterface`): create target
document → create binding + initial status → commit. Repositories participate, never orchestrate. No
external side effects inside the transaction. Idempotent (repeat → `AlreadyCloned`/`TargetAlreadyExists`);
existing target is **never** overwritten; DB uniqueness is the final concurrency guard.

---

## 8. ⛔ BLOCKING DECISION — target-document owner (OPEN)

`pb_layouts.unique(owner_type, owner_id)` means the store **cannot hold a second document for the same
content-owner `(page, content_id)`**, which source and target share. §5 requires the target to receive
its **own** `pb_layouts.id`; §19 forbids a second document store; §25/LT-2 forbid a schema change to
`pb_layouts` (no locale column). Therefore the target document needs a **distinct, non-colliding
`pb_layouts` owner** — a model decision **not dictated by any frozen contract**. Options:

| Option | Approach | Verdict |
|---|---|---|
| **A** | Add locale to `pb_layouts` unique key | ❌ Forbidden (LT-2 guardrail, §25) — mutates the frozen document store; reintroduces owner+locale identity. |
| **B** | New per-locale layout-document table | ❌ Forbidden (§19 "do not create a second layout payload store") + new schema (§25). |
| **C (recommended)** | Target `pb_layouts` row owned by the **Page Translation entity**: `owner_type = 'content_translation'`, `owner_id = ContentTranslation.id` (resolved from Core; guaranteed to exist because eligibility requires the target Page Translation). Distinct from source `(page, content_id)`; **no schema change** (owner columns are already `string`/`bigint`); **not** the owner+locale anti-pattern (owner is a first-class Core entity, and the canonical `LayoutKey` is unchanged); the binding remains the semantic link. Target rows are inert to existing runtime (frontend resolves only `(content->type, content->id)`). | ✅ Satisfies all hard constraints, but is a **new, consequential model** (per-locale documents owned by `ContentTranslation`) that LT-2 deferred and no frozen contract dictates. |

**Recommendation: Option C**, implemented behind a swappable `TargetLayoutWriterInterface` (one class),
so the decision is reversible. **This decision is OPEN pending confirmation** — the write path
(service, adapters, eligibility wiring, tests) is not implemented until it is confirmed, to avoid
building the entire clone edifice on an unconfirmed foundational model.

---

## 9. Planned architecture (on confirmation)

```
LayoutTranslation/Clone/
    Contracts/   LayoutTranslationCloneServiceInterface, SourceLayoutDocumentReaderInterface,
                 TargetLayoutWriterInterface, TransactionRunnerInterface
    Commands/    CloneLayoutTranslationCommand            (canonical identities, not arrays/locale)
    Eligibility/ CloneEligibilityEvaluator, CloneEligibilityResult, CloneIneligibilityReason
    Results/     LayoutTranslationCloneResult, LayoutTranslationCloneOutcome
    Services/    LayoutTranslationCloneService            (owns the transaction)
    Adapters/    PageBuilderSourceLayoutDocumentReader, PageBuilderTargetLayoutWriter (Option C),
                 IlluminateTransactionRunner
    Console/     CloneLayoutTranslationCommand (admin, uses the service, never overwrites)
```

No new schema; reuses `LayoutStore` (target write) and the LT-2 binding repository. No frozen LT-1
contract or LT-2 schema/contract is modified.
