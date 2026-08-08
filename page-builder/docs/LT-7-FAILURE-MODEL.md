# LT-7 — Synchronization Execution Failure Model

**Status: ARCHITECTURE ONLY (LT-7A).** How the execution service maps every failure to a safe, typed
`SynchronizationExecutionOutcome` — never a raw exception, SQL, model, or row. Extends the LT-3
clone failure model to the target-mutation path. Read with
[`LT-7-EXECUTION-ARCHITECTURE.md`](./LT-7-EXECUTION-ARCHITECTURE.md) and
[`LT-7-TRANSACTION-GOVERNANCE.md`](./LT-7-TRANSACTION-GOVERNANCE.md).

---

## Typed outcomes

`SynchronizationExecutionOutcome` (single enum, returned inside `SynchronizationExecutionResult`).

| Outcome | Meaning | Phase / source | Writes? |
|---|---|---|---|
| `Executed` | Approved operations applied to `T`; baseline advanced; one history row appended — atomically. | success | yes (atomic) |
| `NoOp` | Empty plan, or every item kept/ignored → no operation to apply. Success, nothing to do. | A10 | **no** |
| `FeatureDisabled` | `page-builder.translation.enabled` is false. | A1 eligibility | no |
| `CapabilityUnsupported` | The implementation does not advertise `supportsSync`. | A2 eligibility | no |
| `TargetNotBound` | No LT-2 binding for the target page translation. | A3 re-resolve | no |
| `BaselineMissing` | No LT-4 baseline for the target — cannot guard provenance or advance safely. | A4 re-resolve | no |
| `SourceUnavailable` | The current source layout is unreadable / has no valid payload. | A5 re-resolve | no |
| `TargetUnavailable` | The current target document is unreadable. | A6 re-resolve | no |
| `PlanStale` | Current `B`/`S`/`T` fingerprints ≠ the plan's provenance — the plan no longer describes reality. `detail` ∈ {`SourceChanged`,`TargetChanged`,`BaselineChanged`}. | A7 guard | no |
| `AcceptanceInvalid` | An accepted origin is not in the plan, a decision is not permitted, or a reserved change type was accepted. | A8 validation | no |
| `CorrespondenceFault` | An accepted operation cannot resolve its correspondence node/parent in the current `T` (or a remove would touch target-owned nodes). | B3 apply | no (rolled back) |
| `DocumentInvalid` | The applied `T′` fails normalization / schema / correspondence-intact validation. | B4 validation | no (rolled back) |
| `ConcurrentConflict` | Provenance drifted under lock, or a uniqueness/optimistic clash — another writer won the race. | B2 / write normalization | no (rolled back) |
| `IntegrityFault` | A referential/persistence integrity fault (FK, unreadable canonical state). | B5–B7 write normalization | no (rolled back) |
| `PersistenceFailure` | Any other unexpected persistence error (fallback). | write normalization | no (rolled back) |

`NoOp` is a **success** outcome (nothing needed doing); it is distinct from every failure and from
`Executed`. It never advances the baseline and never writes history.

---

## Normalization rules (service boundary)

Mirrors the LT-3 clone normalization discipline; extended for the apply/validate steps.

- **Eligibility / consistency failures (Phase A)** map 1:1 to the outcome above. They occur before the
  transaction opens — read-only, nothing to roll back.
- **In-transaction domain faults** (correspondence unresolved, document invalid, provenance drift under
  lock, baseline/history integrity) are raised as an internal
  `ExecutionConflictException(outcome, detail)` → the transaction rolls back (nothing written) → the
  carried outcome is returned. This is the LT-3 `CloneConflictException` pattern applied to LT-7.
- **`QueryException`** escaping the transaction → message inspected (lower-cased):
  `unique`/`duplicate` → `ConcurrentConflict`; `foreign key`/`foreign_key` → `IntegrityFault`; else
  `PersistenceFailure`.
- **Any other `Throwable`** escaping the transaction → `PersistenceFailure`.

Exception messages are **never** surfaced. A `Throwable` is **never** caught-and-pretended-success. The
result carries only the typed outcome, payload-safe counts/fingerprints, and an optional non-sensitive
`detail` discriminator (e.g. `SourceChanged`) — never a payload, never SQL, never a model.

---

## Rollback model

**One atomic transaction, therefore no compensating logic.** The target write, baseline advance, and
history append are a single unit (LT-7-TRANSACTION-GOVERNANCE.md). Any fault in Phase B rolls the entire
unit back.

A failed (or `NoOp`) execution leaves, provably:

- **Target** — byte-identical to its pre-execution draft (no partial `T′`; INV-LT-02, INV-LT-05).
- **Baseline** — unchanged (not advanced; INV-LT-05).
- **History** — no row written (append-only integrity: a row exists **iff** a sync committed).
- **Source** — untouched at all times (INV-LT-01; source is never read-locked or written).

There is no state in which the target advanced but the baseline did not, or a history row exists without
a corresponding committed target write — the single transaction makes those states unrepresentable.

### Forbidden post-failure states (must be impossible)

- `T′` persisted without baseline advance.
- Baseline advanced without `T′` persisted.
- History row without a committed target write (or vice versa).
- A partially-applied `T′` (some operations applied, some not).
- The full target JSON replaced by source (prohibited in success *and* failure paths).

---

## Recovery

- **Atomic rollback** — a failed execution leaves no partial layout, no advanced baseline, no history
  row (INV-LT-05).
- **Retry** re-resolves canonical persisted state and rebuilds the `ExecutionContext` from scratch
  (LT-3 §4.7); no in-memory state from a prior attempt is reused. A retry after a legitimate source/target
  change will either re-plan cleanly or surface `PlanStale` — never silently double-apply (idempotency,
  LT-7-EXECUTION-ARCHITECTURE §10).
- **`PlanStale` recovery** — the caller re-derives the plan against current `B`/`S`/`T` (LT-5 → LT-6) and
  re-reviews; execution never auto-refreshes a stale plan (that would reinterpret policy).
- **Integrity faults** are observable via `SynchronizationExecutionDiagnostics` and
  `LayoutBaselineDiagnostics`, never auto-repaired.

---

## Fail-closed summary

Every ambiguous, unresolvable, drifted, or unexpected condition results in **no target mutation**. The
default of every gate is *refuse and preserve the target*, never *best-effort apply*. Success is the only
path that writes, and it writes everything or nothing.
