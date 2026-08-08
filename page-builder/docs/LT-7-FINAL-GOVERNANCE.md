# LT-7 — Final Execution Governance (LOCK)

**Status: RATIFIED & LOCKED (LT-7A). Governance only — no code, no schema, no migration.** Ratifies the
two permanent invariants that govern every current and future Layout Translation synchronization
execution. With this lock, the LT-7A architecture
([`LT-7-EXECUTION-ARCHITECTURE.md`](./LT-7-EXECUTION-ARCHITECTURE.md),
[`LT-7-FAILURE-MODEL.md`](./LT-7-FAILURE-MODEL.md),
[`LT-7-TRANSACTION-GOVERNANCE.md`](./LT-7-TRANSACTION-GOVERNANCE.md),
[`LT-7-ARCHITECTURE-REGRESSION.md`](./LT-7-ARCHITECTURE-REGRESSION.md)) is **frozen**. It may be amended
only by a future ADR, never by an implementation phase.

These two invariants are elevated above the design docs: LT-7B and every later execution phase implement
*under* them and may not weaken them.

---

## INV-LT-7-EXEC-01 — Provenance is verified exactly once, before any mutation

**Plan provenance is verified exactly once, before payload mutation begins. Execution never performs
provenance validation after mutation has started. A stale plan always aborts before any mutation.**

- The `PlanProvenance` fingerprint check (B/S/T content-addressed equality) is the **sole and final**
  gate between "reading state" and "mutating the target." It runs under the per-target lock, immediately
  before the first byte of `T′` is produced (Phase B2, before B3).
- Once the operation apply (B3) has begun, provenance is **never** re-checked and **never** relied upon
  again — mutation proceeds on the state that was verified, or it never began.
- A stale plan (`PlanStale` / `ConcurrentConflict` from the provenance check) **always** aborts the
  execution **before any mutation**. There is no code path in which a mutation starts and is then
  unwound because provenance was found stale afterwards. Staleness is a pre-mutation decision, permanently.
- Corollary: the guard is a single point in time, not a repeated poll. Interleaving "check a little,
  mutate a little, check again" is prohibited — it would make partial mutation observable and violate the
  atomic, fail-closed boundary.

**Rationale.** Verifying after mutation begins would require compensating rollback of already-applied
node changes and could expose a partially-synced target. A single pre-mutation verification under the
lock makes staleness a clean abort (target byte-identical, baseline unchanged, no history) and keeps
INV-LT-02/INV-LT-05 mechanically true.

## INV-LT-7-EXEC-02 — History is sufficient to reconstruct execution decisions

**History must be sufficient to reconstruct the execution's decisions. History is not merely an
execution log.**

Every committed execution's history record must preserve, at minimum:

| Required field | Purpose |
|---|---|
| **Plan fingerprint** | Identifies the exact reviewed plan the execution consumed. |
| **Baseline before** | The provenance the sync advanced *from* (reversal anchor). |
| **Baseline after** | The provenance the sync advanced *to* (new source-of-truth). |
| **Accepted operations** | The origins/decisions that mutated the target. |
| **Skipped operations** | The origins/decisions deliberately kept/ignored (a decision, not an omission). |
| **Reviewer decisions** | The per-item resolution that produced accepted vs. skipped. |

- History is a **decision reconstruction record**, not a diagnostic trace. From a history row alone, one
  must be able to answer: *which plan, from which baseline to which baseline, what was accepted, what was
  skipped, and how the reviewer decided each item* — without re-reading any payload.
- **Payload snapshots remain out of scope.** History records origins, enums, decisions, and fingerprints
  only. It never stores node content, `T`, `T′`, or `S`. The payload-safety boundary
  (LT-7-ARCHITECTURE-REGRESSION §4) extends permanently to history: reversal and audit are reconstructed
  from provenance + decisions, never from stored content.
- History remains **append-only** and written **inside** the execution transaction (atomic with the
  target write and baseline advance). A row exists **iff** a sync committed.

**Rationale.** INV-LT-05 requires every applied sync to be reversible via history/baseline. Reversal and
audit therefore depend on *decisions and provenance*, not payloads. Fixing the required fields here — and
excluding payload snapshots — makes the reversibility guarantee durable regardless of how LT-7B/LT-9
implement the history store or a future undo surface.

---

## Scope & precedence

- These invariants bind LT-7B (execution implementation) and every later execution/undo phase. An
  implementation may add fields or capabilities; it may **never** remove a required history field, defer
  provenance verification past the start of mutation, or introduce a mid-mutation re-check.
- They compose with, and never override, the frozen LT-0-ADR-001 invariants (INV-LT-01..07) and the
  LT-3/LT-7 transaction governance. Where any document appears to conflict, this lock and the frozen ADR
  prevail.
- Amendment path: a future ADR only. No implementation phase, test, or refactor may weaken them.

## Freeze

With this governance ratified, **LT-7A Architecture is FROZEN.** The four LT-7A architecture documents
and these two invariants are the authority for LT-7B and beyond. Implementation proceeds under them.
