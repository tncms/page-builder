# LT-7 — Safe Synchronization Execution Architecture

**Status: ARCHITECTURE ONLY (LT-7A). No code, no migration, no schema change, no Core modification, no
frozen-contract change. Execution logic is NOT implemented in this phase.**

Defines the execution layer that consumes the immutable LT-6 `SynchronizationPlan` and applies the
reviewer-approved operations to the **target** layout only (per frozen LT-0-ADR-001 D10). It is the first
layer in the pipeline that mutates a target during synchronization — and it does so transactionally,
target-only, fail-closed, and provenance-guarded.

Companion documents:
[`LT-7-FAILURE-MODEL.md`](./LT-7-FAILURE-MODEL.md) ·
[`LT-7-TRANSACTION-GOVERNANCE.md`](./LT-7-TRANSACTION-GOVERNANCE.md) ·
[`LT-7-ARCHITECTURE-REGRESSION.md`](./LT-7-ARCHITECTURE-REGRESSION.md).

---

## 0. Position in the pipeline

```
LT-4  baseline (B) + correspondence   ─┐
LT-5  three-way diff (B→S, B→T)        ├─ read-only, deterministic, payload-safe
LT-6  SynchronizationPlan (immutable)  ─┘   ← WHAT would happen (origins + enums only, NO content)
LT-7  Execution (this layer)                ← applies the APPROVED subset to the TARGET, atomically
LT-8  Review UI            LT-9  history/undo surfaces
```

LT-7 is the **only** consumer that acts on the plan. Everything upstream is a pure function of payloads;
LT-7 is the boundary where a decision becomes a persisted mutation.

### The payload-safety boundary (decisive constraint)

The LT-6 plan is **payload-safe**: each `SynchronizationPlanItem` carries `{origin, sourceChange,
classification, defaultDecision, conflictReason}` — origins and enums only, **no node content**
(verified in LT-6 with unique content tokens). Therefore:

- The plan alone cannot mutate a document — it does not contain the content to write.
- Execution obtains node content by **reading the current source (S) and target (T) payloads at the
  approved origins** — never from the plan.
- Reading S to fetch approved node content is **not** "recomputing the diff." Execution derives no
  classifications and makes no policy decision; it copies already-approved nodes to approved positions.

This is what makes execution **policy-free**: the plan is the sole authority for *what*; S/T are read
only to obtain the bytes the plan already approved.

---

## 1. Execution pipeline

Single entry point: `LayoutSynchronizationExecutionService::execute(ExecuteSynchronizationCommand)`
→ `SynchronizationExecutionResult` (typed, never a raw exception).

The command is the immutable input bundle:

```
ExecuteSynchronizationCommand {
    target      : PageTranslationKey            // which translation is being synced
    plan        : SynchronizationPlan           // immutable LT-6 plan (verbatim, unmodified)
    provenance  : PlanProvenance                // fingerprints of the exact B, S, T the plan was built from
    acceptance  : SynchronizationAcceptanceSet  // reviewer's explicit per-item decisions
    actor       : ?string                       // audit attribution only
}
```

The pipeline has a strict **pre-transaction (read-only)** phase and a **transaction (atomic)** phase.
No target byte is touched before the transaction phase.

### Phase A — pre-transaction (read-only eligibility, consistency, planning of operations)

| # | Step | Fail-closed exit |
|---|---|---|
| A1 | Feature flag `page-builder.translation.enabled`? | `FeatureDisabled` |
| A2 | Capability `supportsSync()` advertised (A5 resolver)? | `CapabilityUnsupported` |
| A3 | Resolve target binding (LT-2 reader). | `TargetNotBound` |
| A4 | Resolve current baseline `B` (LT-4 repo) — its stored `fingerprint`. | `BaselineMissing` |
| A5 | Read current source payload `S` (LT-3 source reader). | `SourceUnavailable` |
| A6 | Read current target payload `T` (LayoutStore / document locator). | `TargetUnavailable` |
| A7 | **Provenance guard** — `fingerprint(B_now)==provenance.baseline` ∧ `fingerprint(S_now)==provenance.source` ∧ `fingerprint(T_now)==provenance.target`. | `PlanStale` (`SourceChanged` / `TargetChanged` / `BaselineChanged` detail) |
| A8 | Validate `acceptance` against `plan` (every decided origin ∈ plan; decision within permitted bounds). | `AcceptanceInvalid` |
| A9 | **Build operations** — map each *accepted* item to a concrete `SyncOperation` using the current `S` node at that origin + correspondence in `T`. `KeepTarget` / `Ignore` → no operation. | (unresolved refs deferred to B4) |
| A10 | If the operation set is empty (empty plan, or every item kept/ignored) → return early. | `NoOp` (no transaction, no write) |

`S` is **snapshotted once** at A5 and reused for A7, A9, and the baseline advance (B5). This guarantees
the operations applied and the baseline recorded derive from the *same* source state — no read-skew.
Later source edits surface in the *next* synchronization, never mid-execution.

### Phase B — transaction (atomic, target-only; see LT-7-TRANSACTION-GOVERNANCE.md)

| # | Step | Fail-closed exit (→ rollback) |
|---|---|---|
| B1 | Acquire the per-target execution lock (lock the baseline/binding row `FOR UPDATE`). | serialized; no exit |
| B2 | Re-read canonical persisted `T` and `B` under the lock. Re-verify `fingerprint(T)==provenance.target` and `B` fingerprint unchanged vs A4. | `ConcurrentConflict` |
| B3 | Apply operations to a working copy that **starts as `T`** (never as `S`). Each op resolves its correspondence node in `T`. | `CorrespondenceFault` |
| B4 | Normalize + validate the resulting `T′` (schema-valid layout, correspondence intact). | `DocumentInvalid` |
| B5 | Persist: `LayoutStore::saveDraft(owner, T′)` — the single, node-derived target write. | (normalized → integrity exit in failure model) |
| B6 | Advance baseline: upsert `LayoutBaseline(target, sourceLayoutId, fingerprint(S), capturedAt)`. | integrity exit |
| B7 | Append one payload-safe `SyncHistoryRecord`. | integrity exit |
| B8 | Commit — all of B5/B6/B7 or none. | — |

### Phase C — post-commit

Return `SynchronizationExecutionResult::executed(...)`. Any domain event (`LayoutSynchronized`) is
dispatched **only after commit** (LT-3 §4.6). No irreversible side effect runs inside the transaction.

---

## 2. Execution context

Execution derives all state from **canonical persisted sources re-resolved at execute time**; it holds
no state carried over from the planning session except the immutable command inputs. The context object
assembled in Phase A and threaded into the transaction closure:

```
ExecutionContext {
    target            : PageTranslationKey
    binding           : PersistedLayoutTranslationBinding   // LT-2
    baseline          : LayoutBaseline                      // LT-4 (prior B)
    sourcePayload     : array   // S, snapshot at A5 (read-only, never mutated, never persisted as-is)
    targetPayload     : array   // T, re-read canonically under lock at B2
    operations        : SyncOperationSet                    // built at A9 from accepted items
    sourceLayoutId    : int                                 // for baseline advance provenance
    actor             : ?string
}
```

The context is **assembled, not injected** — it is the product of re-resolution, so a retry after a
rollback rebuilds it from scratch (LT-3 §4.7 retry safety). No domain value object (descriptor, keys,
status, plan, operation) is ever coupled to Laravel DB APIs.

---

## 3. Operation model

The plan expresses intent as `(origin, sourceChange)`. Execution lowers each **accepted** item to a
concrete, node-scoped `SyncOperation` — the unit the applier consumes. This is the only place content
enters the pipeline.

### 3.1 `SyncOperationKind` (derived deterministically from `NodeChangeType`)

| Plan `sourceChange` (LT-5) | Accepted → `SyncOperationKind` | Target effect (starts from `T`) |
|---|---|---|
| `Added` | `InsertNode` | insert the S node under the correspondence parent in `T`, at the corresponded sibling position |
| `Removed` | `RemoveNode` | remove the corresponded node from `T` (only if still target-unmodified — see §3.3) |
| `Moved` | `RepositionNode` | move the corresponded `T` node to the S parent/position (content untouched) |
| `StructureUpdated` | `ApplyChildSet` | set the corresponded `T` node's child origin set/order from S |
| `ContentUpdated` | `ReplaceOwnData` | overwrite the corresponded `T` node's **own** data from S (descendants preserved) |
| `Unchanged` | — | never in the plan |

`StyleUpdated` / `SettingUpdated` are LT-5-reserved and never appear; a plan carrying them is rejected
at A8 as `AcceptanceInvalid` (fail-closed, forward-compatible).

### 3.2 `SyncOperation` (immutable VO)

```
SyncOperation {
    kind          : SyncOperationKind
    origin        : string            // stable cross-locale correspondence id (LT-4 data['_origin'])
    parentOrigin  : ?string           // for Insert/Reposition placement
    position      : ?int              // sibling index for deterministic placement
    nodePayload   : ?array            // the approved S node fragment (Insert / ReplaceOwnData only)
    childOrigins  : ?array<string>    // for ApplyChildSet
}
```

`nodePayload` is the sole content-bearing field, populated **from the current S snapshot**, and it exists
**only inside the transaction boundary** — never in the plan, the result, the history record, or
diagnostics (see §4, §9, §13).

### 3.3 Applier — `DocumentOperationApplier` (pure, deterministic)

- Input: the current `T` payload + the ordered `SyncOperationSet`. Output: `T′` or a typed fault.
- The working copy **starts as `T`**. Operations mutate only the corresponded nodes. Every node not
  named by an accepted operation is copied through **verbatim** — target-owned nodes (null origin),
  target-modified nodes whose items were kept, and untouched nodes alike (INV-LT-03 preserved
  mechanically, not by policy).
- Operations are applied in a **deterministic order** (by origin, matching the sorted plan) so identical
  inputs yield a byte-identical `T′`.
- Any operation that cannot resolve its correspondence node/parent in the *current* `T` → the whole apply
  fails `CorrespondenceFault`; nothing partial is emitted. (Example: `RemoveNode` for a node the target
  meanwhile re-parented — fail-closed rather than guess.)
- `RemoveNode` refuses to delete a subtree containing target-owned nodes; such a case is a
  `CorrespondenceFault`, never a silent cascade (INV-LT-03).

---

## 4. Payload mutation boundaries

| Artifact | Read | Mutate | Rule |
|---|---|---|---|
| **Source `S`** | yes (snapshot, to fetch approved node bytes) | **never** | INV-LT-01. No lock, no write, ever. |
| **Baseline `B`** | yes (guard + provenance) | **advance only, post-apply, in-tx** | Advancing is recording a new content-addressed snapshot, not editing history; the prior fingerprint is preserved in history (§13). |
| **Target `T`** | yes | **yes — the only mutation target** | Node-scoped; working copy starts from `T`; single `saveDraft(T′)`. |
| Target-owned / kept nodes | copied through | **never touched** | INV-LT-03 enforced by the applier. |
| Translated content | preserved by default | overwritten **only** on an explicitly-accepted `ContentUpdated` item | INV-LT-04: never *auto*-overwritten; explicit reviewer acceptance is a human decision, not automation. |
| Full target JSON | — | **never replaced wholesale** | INV-LT-02. `T′` is `T`-derived; S is never copied over T as a document. Prohibited and regression-tested (LT-7-ARCHITECTURE-REGRESSION.md). |

**Content never leaves the transaction.** `nodePayload` is materialized in Phase B and discarded on
commit/rollback. Nothing content-bearing is returned, logged, or persisted outside `pb_layouts.draft`.

---

## 5. Validation boundaries

Two tiers, mirroring the LT-3 eligibility/in-transaction split.

**Pre-transaction (read-only, no side effects)** — steps A1–A9:
feature flag, capability, binding existence, baseline existence, source/target readability, **provenance
consistency** (A7), **acceptance validity** (A8: accepted origins ⊆ plan; decisions permitted;
reserved change types rejected), and operation buildability.

**In-transaction (under lock, before any write)** — steps B2–B4:
re-verify provenance fingerprints under lock (`ConcurrentConflict` on drift), correspondence resolution
of every operation (`CorrespondenceFault`), and **post-apply document validity** — `T′` must pass the
same normalization/schema validation the canonical mutation path (`LayoutStore::saveDraft`) already
enforces, plus a correspondence-intact check (no origin collision, no orphaned insert). A failed
validation aborts the transaction; the target is left byte-identical.

Validation is **fail-closed at every gate**: an unrecognized, ambiguous, or unresolvable condition aborts
with a typed outcome; it never degrades to a best-effort partial write.

---

## 6. Transaction ownership

Summarized here; governed in full by [`LT-7-TRANSACTION-GOVERNANCE.md`](./LT-7-TRANSACTION-GOVERNANCE.md).

The application service `LayoutSynchronizationExecutionService` **owns one transaction** covering
`saveDraft(T′)` + baseline advance + history append (all commit or none). It reuses the LT-3
`TransactionRunnerInterface`. Repositories (`LayoutStore`, `LayoutBaselineRepository`,
`SyncHistoryRepository`) **participate**; none begins/commits the workflow or opens its own transaction.
No new Unit-of-Work framework. External side effects only after commit.

---

## 7. Failure model

See [`LT-7-FAILURE-MODEL.md`](./LT-7-FAILURE-MODEL.md). Every failure maps to a typed
`SynchronizationExecutionOutcome`; raw exceptions, SQL, models, and rows never surface. Fail-closed: any
failure leaves the target, baseline, and history unchanged (INV-LT-05).

## 8. Rollback model

See [`LT-7-FAILURE-MODEL.md`](./LT-7-FAILURE-MODEL.md). Rollback = the single database transaction
rolling back; no compensating logic exists because there is exactly one atomic unit. A failed execution
produces no partial layout, no advanced baseline, and no history row.

---

## 9. Result contracts

`SynchronizationExecutionResult` — immutable, payload-safe (origins + enums + counts + fingerprints
only; **no node content**), mirroring LT-3/LT-4/LT-6 result style.

```
SynchronizationExecutionResult {
    outcome            : SynchronizationExecutionOutcome
    appliedCount       : int
    skippedCount       : int              // plan items kept/ignored (present for audit, unchanged)
    newBaselineFinger  : ?string          // set only on Executed
    historyId          : ?int             // set only on Executed
    detail             : ?string          // non-sensitive drift/fault discriminator (e.g. SourceChanged)
}
```

Named constructors: `::executed(applied, skipped, fingerprint, historyId)`, `::noop()`,
`::stale(detail)`, `::ineligible(outcome)`, `::failure(outcome, detail = null)`. The result never carries
an exception message and never carries a payload.

---

## 10. Idempotency

Idempotency is a **natural consequence of the baseline advance**, not an idempotency key (mirrors the A0.3
checkout pattern — correctness lives in a state transition):

- After a successful `Executed`, the baseline `B` equals the synced source `S`.
- Re-submitting the same command re-reads `B == S` at A4/A5. The provenance guard now sees `B_now`
  advanced past `provenance.baseline` → returns `PlanStale` (`BaselineChanged`). The plan cannot be
  applied twice.
- A caller who re-plans against the new baseline gets an **empty plan** (S == B) → `NoOp`. No write.

Thus replay is safe by construction: the second attempt is either `PlanStale` (old plan) or `NoOp`
(fresh plan). No double-application is possible.

---

## 11. Concurrency

- **Per-target serialization.** Phase B opens by locking the target's baseline/binding row `FOR UPDATE`
  (B1). Two executions on the same target serialize; the second re-reads the advanced baseline and exits
  `ConcurrentConflict` (or `PlanStale` in Phase A) — never a lost update, never an interleaved write.
- **Cross-target isolation.** Executions on different targets never contend (distinct lock rows). Source
  is shared but read-only, so concurrent syncs of many locales from one source are safe.
- **Provenance compare-and-verify under lock (B2)** is the optimistic guard layered on the pessimistic
  lock: if any input drifted between Phase A and Phase B, the write is refused.
- **Retry safety (LT-3 §4.7).** A rollback + retry re-resolves canonical persisted state and rebuilds the
  `ExecutionContext`; no in-memory state from a prior attempt is reused. Database uniqueness
  (`UNIQUE(page_translation_key)` on baseline) is the final concurrency guard, normalized to
  `ConcurrentConflict`.

---

## 12. Diagnostics

`SynchronizationExecutionDiagnostics::report(PageTranslationKey)` — read-only, never-throws,
non-repairing (mirrors `LayoutBaselineDiagnostics`). Reports whether an execution *would* be blocked and
why, **without** mutating anything or emitting payloads:

- feature flag / capability state,
- binding presence, baseline presence,
- **staleness signal**: `fingerprint(S_now)` vs the stored baseline fingerprint (in-sync / source-ahead),
  and `fingerprint(T_now)` vs a supplied provenance if any,
- correspondence coverage on `T` (orphaned / ambiguous origins that would fault an apply).

Diagnostics is strictly separate from execution: it decides nothing and writes nothing. It is the
read-only introspection surface for LT-8's review UI and for support tooling.

---

## 13. History integration

Append-only synchronization history (per D10). Additive, plugin-owned, **created-not-executed** in this
phase (migration authored, not run — same discipline as LT-4's baseline table).

- **Table** `pb_layout_translation_sync_history` (additive): `id`, `page_translation_key`,
  `actor` (nullable), `prior_baseline_fingerprint`, `new_baseline_fingerprint`, `source_layout_id`,
  `applied_count`, `skipped_count`, `applied_items` (JSON: array of `{origin, source_change, decision}` —
  **payload-safe, origins + enums only, no content**), `executed_at`. Index `(page_translation_key,
  executed_at)`. **No FK to `pb_layouts`** (content-addressed provenance; source stays independently
  deletable — consistent with LT-4 baseline storage).
- **Append-only invariant.** Rows are only ever inserted — never updated, never deleted. One row per
  successful `Executed`. `NoOp` and all failures write nothing.
- **Written inside the execution transaction (B7)** — atomic with the target write and baseline advance.
- **Reversibility (INV-LT-05).** The row captures the *prior* baseline fingerprint and the exact applied
  origins + decisions, which is sufficient data to reconstruct and reverse a sync. LT-7A guarantees the
  *data is captured to make reversal possible*; the revert *operation itself* is a later surface
  (LT-9), not implemented here.

### Baseline-advance semantics (decision, with tradeoff surfaced)

On success the baseline advances to **the full current source `S`** (`fingerprint(S)`), even when the
reviewer kept some source changes (`KeepTarget` on a conflict).

- **Why:** D7 defines `B` as "the source's state at the last successful sync." `B` must equal a *real*
  source payload for the content-addressed fingerprint model to hold; a partial/synthetic baseline would
  correspond to no real source and break comparability with future `S`.
- **Consequence:** deliberately-kept source changes do **not** resurface in the next sync (the reviewer
  already decided). Their provenance is not lost — it is recorded in the append-only history row
  (`applied_items` shows the `KeepTarget` decisions).
- **Rejected alternative:** advancing `B` only over accepted origins. Rejected because it yields a
  baseline that hashes to no actual source document, defeating LT-4's `BaselineFingerprint` invariant and
  making every subsequent three-way diff ambiguous.

---

## 14. Architecture regression

See [`LT-7-ARCHITECTURE-REGRESSION.md`](./LT-7-ARCHITECTURE-REGRESSION.md) for the full invariant
preservation matrix, contract-freeze verification, payload-safety proof obligations, and the
full-document-replacement prohibition.

---

## Layering & placement

New subtree `src/LayoutTranslation/Execution/` (sibling of `Cloning/`, `Baseline/`, `Diff/`, `Plan/`):

```
Execution/
  Contracts/    SynchronizationExecutionServiceInterface, OperationApplierInterface,
                SyncHistoryRepositoryInterface  (reuse LT-3 TransactionRunnerInterface,
                SourceLayoutDocumentReaderInterface; LT-4 LayoutBaselineRepositoryInterface)
  Commands/     ExecuteSynchronizationCommand
  Provenance/   PlanProvenance
  Acceptance/   SynchronizationAcceptanceSet, AcceptedItemDecision
  Operations/   SyncOperation, SyncOperationKind, SyncOperationSet, OperationBuilder,
                DocumentOperationApplier
  Eligibility/  ExecutionEligibilityEvaluator, ExecutionIneligibilityReason
  Services/     LayoutSynchronizationExecutionService, ExecutionConflictException
  Results/      SynchronizationExecutionResult, SynchronizationExecutionOutcome
  History/      SyncHistoryRecord, Models/…, Repositories/…   (migration created-not-executed)
  Diagnostics/  SynchronizationExecutionDiagnostics
  Adapters/     Eloquent/Illuminate implementations of the contracts
```

Dependency direction: **Translation → Page Builder only** (consumes the generic `LayoutStore` and the
frozen LT-2/LT-3/LT-4 seams). No Page Builder core or CMS Core file is aware of the Execution layer.

## Non-goals (LT-7A)

No execution *logic* (this is architecture only); no `SyncOperation` payload mutation implemented; no
review UI (LT-8); no undo/revert operation (LT-9); no auto-resolution of conflicts; no Style/Setting
field taxonomy; no schema migration executed; no Core change; no frozen-contract change.

## Mandatory invariants restated

Execution consumes an immutable `SynchronizationPlan` · execution is policy-free (derives no
classification; reads S only to fetch approved bytes) · fail-closed at every gate · target-only ·
never mutates Source · never mutates Baseline except the post-success advance · history is append-only ·
every execution is fully transactional.
