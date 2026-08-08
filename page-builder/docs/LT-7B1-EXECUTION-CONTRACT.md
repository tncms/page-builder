# LT-7B1 — Execution Contract Layer

**Status: IMPLEMENTED & FROZEN (LT-7B1). Immutable value contracts only — no mutation, no persistence,
no transaction, no history, no schema, no migration, no Core change.** The first LT-7B increment: it
freezes the immutable execution contract *before any payload mutation exists*, so LT-7B2 (the Mutation
Engine) and later phases build against a fixed, verified boundary. Governed by the frozen LT-7A
architecture and [`LT-7-FINAL-GOVERNANCE.md`](./LT-7-FINAL-GOVERNANCE.md).

---

## Delivered contracts (`src/LayoutTranslation/Execution/`)

| Contract | Kind | Role |
|---|---|---|
| `Operations/SyncOperationKind` | enum | The closed set of node-scoped target mutations; self-describing shape (`carriesNodePayload/Placement/ChildOrigins`). No policy, no `NodeChangeType` mapping (that is LT-7B2). |
| `Operations/SyncOperation` | VO | One immutable, self-contained, serializable operation. Private ctor + per-kind named ctors + `toArray`/`fromArray`. The **only** content-bearing contract (`nodePayload`). |
| `Operations/SyncOperationSet` | VO | Immutable, origin-sorted, dedup-guarded work list. Deterministic application order. |
| `Provenance/ProvenanceDrift` | enum | `BaselineChanged` / `SourceChanged` / `TargetChanged` — the `PlanStale` detail discriminator. |
| `Provenance/PlanProvenance` | VO | Content-addressed B/S/T fingerprints binding a plan to its state. `verify()` = fixed-order constant-time equality (`hash_equals`). Payload-safe. |
| `Acceptance/AcceptedItemDecision` | VO | Reviewer's resolved decision for one origin (reuses frozen LT-6 `SynchronizationDecision`). |
| `Acceptance/SynchronizationAcceptanceSet` | VO | Immutable, origin-sorted, dedup-guarded per-item decisions; `acceptedOrigins()` filter. Payload-safe. |
| `Results/SynchronizationExecutionOutcome` | enum | The 15 typed outcomes (2 success, 13 fail-closed); `isSuccess/isFailure/didMutate`. |
| `Results/SynchronizationExecutionResult` | VO | Immutable, payload-safe result (outcome + counts + fingerprint + history id + detail). Private ctor + named ctors. |

No service, applier, repository, model, migration, or transaction is introduced. Nothing here reads or
writes any document.

---

## Ratified permanent invariants

Elevated to permanent status (amendable only by a future ADR), alongside INV-LT-7-EXEC-01/02.

### INV-LT-7-OPS-01 — SyncOperation is pure, self-contained execution data

**A `SyncOperation` is immutable, deterministic, and self-contained-serializable. It never keeps a
reference to the Source, Target, Baseline, a repository, a callback, or a closure. It is pure execution
data.**

- Immutable: all properties `readonly`; the constructor is private; only per-kind named constructors (and
  a validating `fromArray`) can build one, so a malformed operation cannot exist.
- Deterministic + serializable: `toArray` → `json_encode` → `fromArray` restores a byte-identical
  operation (verified). Because it holds only scalars/arrays/enums, it carries no closures and survives
  transport and journaling.
- No live references: it names nodes by **origin** (a stable id string) and carries the approved node
  bytes **as an array**, never a handle to a document, model, service, or callable.

### INV-LT-7-OPS-02 — the Mutation Engine consumes only Target + SyncOperation[]

**The future Mutation Engine consumes only the Target and a `SyncOperation[]`. It never reads the
Source, the Baseline, or the `SynchronizationPlan`. Every piece of information required to execute an
operation already exists inside the `SyncOperation`.**

This preserves the one-way layering `Plan ↓ Operation ↓ Mutation`: lowering a plan item into an
operation is the *only* bridge from decision to mutation. Because operations are self-contained, no later
phase can reach back past the operation boundary to re-read the Source/Baseline/Plan — the execution
boundary cannot be bypassed. The engine's entire input surface is `(Target, SyncOperationSet)`.

> LT-7B1 encodes these structurally: `SyncOperation`/`SyncOperationSet` expose no Source/Baseline/Plan
> field and no callable. The Mutation Engine itself is LT-7B2; this increment guarantees its input
> contract can carry everything it will need.

---

## Architecture regression verification

| Obligation (LT-7A) | Result |
|---|---|
| No mutation / persistence / transaction / history / schema / migration | **Confirmed** — value classes only; no service, model, or DB touch. |
| No Core change; no frozen-contract change | **Confirmed** — Translation→Page Builder only; reuses frozen LT-6 `SynchronizationDecision`; LT-1…LT-6 untouched. |
| Payload-safety (LT-7-ARCH-REGRESSION §4) | **Confirmed** — a unique content token appears **only** in `SyncOperation`/`SyncOperationSet` transport; `Result`, `AcceptanceSet`, and `PlanProvenance` `toArray` are content-free (verified). |
| Determinism (LT-7-ARCH-REGRESSION §6) | **Confirmed** — operation and acceptance sets are origin-sorted; identical inputs → byte-identical serialization. |
| INV-LT-7-OPS-01 self-containment | **Confirmed** — json round-trip proves no closures/references; only scalars/arrays/enums. |
| INV-LT-7-OPS-02 input boundary | **Confirmed by construction** — operation carries payload/placement/child-set inline; no Source/Baseline/Plan field exists. |
| Fail-closed construction | **Confirmed** — empty origins, missing payloads, and duplicate origins are rejected at construction/deserialization. |

**Standalone verification: 26/26 passed** (named-ctor shapes, serialization round-trip, fail-closed
construction, set ordering/dedup, provenance fixed-order equality, acceptance filtering/dedup, result
outcome semantics, payload-safety boundary). `php -l` clean on all nine files. Verification was run via a
temporary standalone harness and removed after passing (no test file committed in this increment).

---

## Non-goals (LT-7B1)

No `OperationBuilder` (plan-item → operation lowering) or `DocumentOperationApplier` — that is **LT-7B2**.
No execution service, eligibility, provenance guard wiring, transaction, baseline advance, history store,
diagnostics, migration, or Core change. This increment freezes contracts only.

**LT-7B1 contracts are frozen. Proceed to LT-7B2 (Mutation Engine) under INV-LT-7-OPS-01/02.**
