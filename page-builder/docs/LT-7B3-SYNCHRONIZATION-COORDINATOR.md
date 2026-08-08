# LT-7B3 — Synchronization Coordinator

**Status: IMPLEMENTED & FROZEN (LT-7B3). Orchestration layer over frozen seams. No concrete DB adapter
executed and no migration run in this increment (created-not-executed discipline); the coordinator is
verified against its ports with in-memory fakes.** The third LT-7B increment: the component that owns the
entire synchronization workflow and drives the pure LT-7B2 engine — which remains completely unaware of
it. Named **Coordinator** (not "Transactional Execution") because its responsibility is *orchestration*,
of which transaction ownership is only one part. Governed by the frozen LT-7A architecture and
[`LT-7-FINAL-GOVERNANCE.md`](./LT-7-FINAL-GOVERNANCE.md).

---

## Delivered (`src/LayoutTranslation/Execution/`)

| Component | Kind | Role |
|---|---|---|
| `Services/SynchronizationCoordinator` | service | Owns eligibility → load → provenance → lowering → mutation → persist → advance → history → typed result. |
| `Contracts/SynchronizationCoordinatorInterface` | interface | `execute(command): SynchronizationExecutionResult`. |
| `Commands/ExecuteSynchronizationCommand` | VO | target + plan + provenance + acceptance + actor. |
| `Loading/LoadedSynchronizationState` + `Contracts/SynchronizationStateLoaderInterface` | VO + port | Canonical persisted state; `loadForUpdate` locks (INV-LT-7-EXEC-01), `load` is lock-free (diagnostics). |
| `Operations/OperationBuilder` (+ `SourceLayoutIndex`, `OperationLoweringException`) | lowering | The only bridge plan-item → `SyncOperation`; reads source for approved bytes; reinterprets no policy. |
| `Validation/AcceptanceValidator` | validator | Pure, pre-transaction: rejects unknown origins / unlowerable change types. |
| `Contracts/{ExecutionEligibility,TargetDraftWriter,BaselineAdvancer,SyncHistoryRecorder}Interface` | ports | The coordinator's owned seams over feature/capability, `saveDraft`, LT-4 baseline upsert, and append-only history. |
| `History/SyncHistoryRecord` | VO | Payload-safe decision record (INV-LT-7-EXEC-02). |
| `Services/ExecutionConflictException` | exception | In-transaction typed abort → rollback → typed outcome. |
| `Diagnostics/SynchronizationExecutionDiagnostics` | reporter | Read-only, never-throws would-block + source-ahead report. |

Reuses the frozen LT-3 `TransactionRunnerInterface`, LT-4 `BaselineFingerprint`, LT-6 plan/decision, and
the LT-7B2 `OperationApplierInterface`. The concrete Eloquent adapters binding these ports to `LayoutStore`
/ the LT-2 binding reader / the LT-4 repository / a new `pb_layout_translation_sync_history` table
(created-not-executed) are the **wiring step**, deliberately out of this increment.

## Execution flow (honouring INV-LT-7-EXEC-01)

```
feature? capability?           ── pre-transaction, read-only
acceptance valid?              ── pure (AcceptanceInvalid on unknown origin / unlowerable change)
plan empty || none accepted?   ── NoOp: no transaction, no write
└─ transaction.run:
     load FOR UPDATE                       (TargetNotBound / BaselineMissing / Source|TargetUnavailable)
     verify provenance EXACTLY ONCE        (PlanStale — before any mutation, under the lock)
     lower accepted items → operations     (AcceptanceInvalid on lowering fault)
     apply(target, operations) → T′        (CorrespondenceFault — first mutation byte, only now)
     saveDraft(T′) → advance baseline → append history   (atomic)
   commit
```

Verified order: `load → apply → write → advance → history`, all in one transaction; any abort rolls back
with the target, baseline, and history unchanged.

---

## Ratified permanent invariants

Elevated to permanent status (amendable only by a future ADR), alongside INV-LT-7-EXEC-01/02 and
INV-LT-7-OPS-01..04. They constrain the **Mutation Engine** (LT-7B2), which this increment leaves
untouched.

### INV-LT-7-MUT-01 — the Mutation Engine is a pure function

**The Mutation Engine may not access time, configuration, locale, user, request, the service container,
the database, or repositories. Its only inputs are Target + `SyncOperation[]`; its only output is `T′`.**

- Confirmed: `DocumentOperationApplier` has **no constructor and no dependencies** — it is instantiated
  with `new` and holds no collaborators. Its sole public method is `apply(array $target,
  SyncOperationSet): array`. It reads nothing but its two arguments.
- Every environmental concern named above lives in the **coordinator**: the coordinator reads config
  (feature flag) and capability, loads state, computes fingerprints, owns the transaction, and persists.
  The engine is handed a plain array and a set of pure operations and returns a plain array.

### INV-LT-7-MUT-02 — the Mutation Engine has no observable side effects

**The Mutation Engine never logs, emits events, records metrics, invokes hooks, or executes callbacks.
Telemetry belongs to the coordinator.**

- Confirmed: the engine performs no logging, event, metric, hook, or callback invocation. It produces a
  value and, on an unresolvable operation, throws a typed `OperationApplicationException` — a pure control
  signal, not telemetry.
- Clarification: the engine uses **private internal closures** purely as a tree-traversal mechanism
  (`walkReplace`). These are implementation detail — not injected, not observable, and side-effect-free.
  The invariant targets externally-observable effects (telemetry/hooks/events) and injected behaviour;
  internal control flow is not a "callback" in that sense. All telemetry and eventing — including any
  post-commit `LayoutSynchronized` event — are the coordinator's responsibility, dispatched only after a
  successful commit (LT-7-TRANSACTION-GOVERNANCE §7.6).

---

## Architecture regression verification (before the next increment)

| Obligation | Result |
|---|---|
| Coordinator owns load/provenance/eligibility/mutation/persistence/history/transaction/diagnostics/results | ✅ all in `SynchronizationCoordinator`; engine unaware |
| INV-LT-7-EXEC-01 provenance verified once, before mutation | ✅ stale plan → `PlanStale`, engine **never invoked**, zero writes |
| INV-LT-7-EXEC-02 history reconstructs decisions, payload-safe | ✅ record carries plan fingerprint, baseline before/after, applied + skipped items with reviewer decisions; no content token present |
| INV-LT-7-MUT-01/02 engine purity + no side effects | ✅ engine has no deps, no I/O, no telemetry; all environment in coordinator |
| Atomic write set (LT-7-TRANSACTION-GOVERNANCE) | ✅ `write → advance → history` inside one transaction; all-or-nothing |
| Fail-closed, no partial writes | ✅ every gate (`FeatureDisabled`/`CapabilityUnsupported`/`TargetNotBound`/`BaselineMissing`/`SourceUnavailable`/`AcceptanceInvalid`/`PlanStale`/`CorrespondenceFault`) → no target write, no baseline advance, no history |
| INV-LT-02/03/04 preserved end-to-end | ✅ source B1 content applied while target-owned node stays byte-identical; skipped conflict leaves target |
| Idempotency (baseline advance) | ✅ baseline advances to `fingerprint(S)`; a replayed plan then trips `PlanStale` (provenance) |
| No Core / frozen-contract change | ✅ reuses LT-3/LT-4/LT-6/LT-7B1/B2; nothing upstream modified |

**Standalone verification: 25/25 passed** (full success + write payload; decision-record history +
payload-safety; provenance-before-mutation on stale; NoOp without transaction; feature/capability/state
gates; AcceptanceInvalid; CorrespondenceFault with no writes). `php -l` clean across all 29 Execution
files. Verified with in-memory fakes via a temporary harness, removed after passing.

---

## Non-goals (LT-7B3)

No concrete Eloquent adapters bound to `LayoutStore`/LT-2/LT-4, no `pb_layout_translation_sync_history`
migration executed, no service-provider registration, no review UI (LT-8), no undo/revert (LT-9), no Core
change. Those are the wiring/UI increments; this increment delivers the orchestration layer and its ports,
verified against fakes.

**LT-7B3 is frozen.** The Execution pipeline (contracts → engine → coordinator) is complete and
invariant-locked; only concrete persistence wiring and surfaces remain.
