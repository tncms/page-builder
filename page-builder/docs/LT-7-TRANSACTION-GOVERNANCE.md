# LT-7 — Execution Transaction Governance (LOCK)

**Status: ARCHITECTURE ONLY (LT-7A), governs the atomic write boundary of synchronization execution.**
Extends [`LT-3-TRANSACTION-BOUNDARY-GOVERNANCE.md`](./LT-3-TRANSACTION-BOUNDARY-GOVERNANCE.md) from the
clone write to the target-mutation write. Where LT-3 governs *creating* a target, LT-7 governs *mutating*
one. The rules are identical in spirit; this document states the LT-7-specific atomic set and locking.

---

## 7.1 Transaction owner

The **application-level execution service** owns the transaction boundary:

```
LayoutSynchronizationExecutionService  (owns transaction)
    → lock the target's baseline/binding row (FOR UPDATE)
    → re-verify provenance under lock
    → saveDraft(T′)                         (LayoutStore participates)
    → advance baseline (upsert)             (LayoutBaselineRepository participates)
    → append history row                    (SyncHistoryRepository participates)
    → commit
```

Repositories and the `LayoutStore` **participate** in the transaction; they do **not** orchestrate the
use case, begin/commit the workflow, or open their own transaction.

## 7.2 Repository / store responsibility

A participant may write its own aggregate, enforce local invariants, and return a typed result. A
participant must **not**: begin/commit the workflow, call another participant to implement the business
workflow, apply operations, re-derive plans, resolve eligibility, advance provenance on its own, or
swallow a failure to preserve a partial write. `LayoutStore::saveDraft` is used as the canonical target
mutation path exactly as LT-3/LT-4 use it — a single statement composing safely inside the service
transaction (LT-3 §4.5 audit result stands).

## 7.3 Atomicity

One atomic transaction covers, on success:

1. **Target draft write** — `saveDraft(owner, T′)`, where `T′` is the `T`-derived, node-scoped result.
2. **Baseline advance** — upsert `LayoutBaseline(target, sourceLayoutId, fingerprint(S), capturedAt)` on
   the unique `page_translation_key`.
3. **History append** — one payload-safe `SyncHistoryRecord` row.

**All commit or none commit.** The forbidden post-failure states are enumerated in
[`LT-7-FAILURE-MODEL.md`](./LT-7-FAILURE-MODEL.md) (target-without-baseline-advance,
baseline-advance-without-target, history-without-write, partial `T′`, full-JSON replacement). The single
transaction makes each unrepresentable.

## 7.4 Transaction infrastructure

Reuse the LT-3 `TransactionRunnerInterface` at the application-service boundary. Domain value objects
(command, provenance, acceptance set, operations, result, history record, keys) are **never** coupled to
Laravel DB APIs. **No** generalized Unit-of-Work framework is introduced.

## 7.5 Concurrency & locking

- **Pessimistic per-target lock.** The transaction opens by selecting the target's baseline (or binding)
  row `FOR UPDATE`, serializing all executions for that target. This is the concurrency spine; cross-
  target executions never contend.
- **Optimistic provenance re-verify under lock.** After acquiring the lock, the service re-reads the
  canonical `T` and `B` and re-checks their fingerprints against `provenance` (Phase B2). Drift →
  `ConcurrentConflict`, rollback. The lock removes the race window; the fingerprint re-check is the
  belt-and-braces guarantee that nothing changed between Phase A resolution and the locked read.
- **Database uniqueness** (`UNIQUE(page_translation_key)` on the baseline table) is the final guard,
  normalized to `ConcurrentConflict` at the service boundary.
- **Nested transactions.** Correctness must **not** rely on savepoint/nested-transaction behaviour (not
  identical across SQLite and MySQL) — LT-3 §4.5. All participants write single statements inside the
  one open transaction.

## 7.6 External side effects

**No irreversible external side effect inside the transaction**: no notifications, webhooks, queue jobs,
analytics, cache busting, or remote calls. A domain event (`LayoutSynchronized`) is dispatched **only
after a successful commit**, via the existing after-commit mechanism. **No outbox** is built.

## 7.7 Retry safety

A rollback followed by a retry is safe. The workflow holds **no** in-memory state from a prior attempt;
every retry re-resolves canonical persisted state and rebuilds the `ExecutionContext` (LT-3 §4.7). The
per-target lock plus baseline uniqueness remain the final concurrency guards, normalized to a typed
`ConcurrentConflict`/`PlanStale` outcome.

## 7.8 Source & baseline discipline within the boundary

- The **source is never inside the write boundary** — it is read once, pre-transaction, into an immutable
  snapshot (INV-LT-01). It is never locked, never written, and its later mutation cannot corrupt an
  in-flight execution because both the operations and the baseline advance derive from the same snapshot.
- The **baseline is read-guarded, then advanced once** at the end of the transaction. The advance records
  the source snapshot's fingerprint; the *prior* fingerprint is preserved in the history row, so the
  advance adds provenance without destroying it (INV-LT-05 reversibility data).

## 7.9 What LT-7A does not do

Architecture only: no transaction code is written, no migration is executed. The history and (if needed)
baseline-advance migrations are **created, not run** — consistent with LT-4's created-not-executed
discipline. Implementation of the boundary is a later phase, bound by this governance.
