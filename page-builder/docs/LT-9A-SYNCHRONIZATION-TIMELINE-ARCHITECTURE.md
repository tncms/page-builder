# LT-9A — Synchronization Timeline Architecture

**Status: ARCHITECTURE ONLY (LT-9A). No code, no UI, no runtime changes, no coordinator change, no
frozen-contract change.** Designs the Synchronization Timeline — the read layer that turns the append-only
LT-7 history and the immutable LT-8.5 snapshots into a navigable, reversible, explainable account of every
synchronization on a target. It **consumes** the frozen pipeline and the snapshot capability; it mutates
nothing. **Stop after architecture.**

---

## 0. Position — a read layer over frozen facts

```
   LT-4 baseline (current fingerprint per target)
   LT-7 history  (append-only, payload-safe: plan_fingerprint, baseline_before/after,
                  source_layout_id, applied_items/skipped_items {origin,source_change,decision}, actor, executed_at)
   LT-8.5 snapshots (immutable, content-addressed documents by fingerprint; roles Baseline/Source/Target)
   LT-5 diff / LT-6 plan / LT-7 pure applier  (frozen, deterministic — reused read-only)
            │
            ▼
   ┌─────────────────────────────────────────────────────────────────────┐
   │  LT-9A  SYNCHRONIZATION TIMELINE  (read layer)                       │
   │                                                                     │
   │  Timeline ── Replay(sandbox) ── Reversal plans(Undo/Rollback) ──    │
   │           ── Audit ── Explainability      all via Snapshot          │
   │                                            consumption (read-only)   │
   └─────────────────────────────────────────────────────────────────────┘
```

The timeline **reads** history and snapshots. It never writes history, never mutates a snapshot, never
touches the sync coordinator. Where an operation is inherently mutating (Undo, Rollback), LT-9A designs it
as an immutable **reversal plan** — a *description* of what would be restored — exactly as LT-6 designs a
plan that LT-7 later executes. No execution, and no runtime change, happens here.

---

## 1. Design principles (proposed invariants)

| # | Invariant |
|---|---|
| **INV-LT-9-TL-01** | **Append-only, never rewrite.** The timeline is a read projection of append-only history. Undo and Rollback are recorded as **new compensating events**, never as deletions or edits of past entries. The past is immutable (mirrors INV-LT-7-EXEC-02, INV-LT-8.5-07). |
| **INV-LT-9-TL-02** | **No frozen path mutated.** Replay is read-only/sandboxed (the frozen pure applier over reconstructed inputs, never a live write). Undo/Rollback are designed as reversal *plans*; their eventual execution is an **additive new operation**, never an edit to the sync coordinator or history writer. LT-9A itself writes nothing. |
| **INV-LT-9-TL-03** | **Snapshot consumption is read-only, single-path, fail-soft.** Every document is obtained through the one LT-8.5 store (INV-LT-8.5-06). Missing content makes an operation **Unavailable** (and explained), never fabricated; a re-hash mismatch fails closed (INV-LT-8.5-04). |
| **INV-LT-9-TL-04** | **Explainability is reconstructive and graceful.** It re-derives facts from retained data (history + snapshots + the frozen LT-5/LT-6) and degrades: *full* (content + re-derived classification) when snapshots exist, *metadata-only* otherwise. It re-injects nothing into history, plan, or provenance. |
| **INV-LT-9-TL-05** | **Reference identity ≠ blob identity.** The timeline references documents by `BaselineFingerprint` (as recorded on history rows) and resolves them via `ContentDigest` through the store; it never conflates the two (INV-LT-8.5-08). |
| **INV-LT-9-TL-06** | **Baseline-chain continuity.** Entry N's `baseline_after` equals entry N+1's `baseline_before`; a break is surfaced as an explicit **gap**, never silently bridged or reordered. |

---

## 2. Timeline

- **`SynchronizationTimeline`** for a target = the entries derived from that target's history rows, ordered
  by `(executed_at, id)`. A pure read projection; it appends as history appends and never rewrites.
- **`TimelineEntry`** (payload-safe metadata, straight from a history row): `id`, `executedAt`, `actor`,
  `kind` (see below), `planFingerprint`, `baselineBefore`, `baselineAfter`, `sourceLayoutId`,
  `appliedCount`, `skippedCount`, `appliedItems`/`skippedItems` (`{origin, source_change, decision}`), plus
  **derived document references** (`baselineBefore`, `baselineAfter`, and role-scoped target references)
  that resolve to documents via the snapshot store — never embedded content.
- **`TimelineEntryKind`** — `Sync` (a committed LT-7 execution) and, once reversal execution lands as a
  later additive phase, `Undo` / `Rollback` compensating entries. In LT-9A only `Sync` exists in history;
  the other kinds are designed so the model is closed and forward-compatible.
- **Baseline chain (INV-LT-9-TL-06).** The timeline validates that each entry's `baseline_after` == the
  next entry's `baseline_before`, forming a verifiable provenance chain from the first baseline to the
  current one. A discontinuity (e.g. an out-of-band re-baseline, or GC'd history) is reported as a typed
  **`TimelineGap`**, not hidden.
- The timeline is **read-only and deterministic**: identical history + snapshots → identical timeline. It
  holds no state of its own; it is rebuilt on demand (disposable, like the review projections).

## 3. Replay

- **Replay = deterministic re-execution of a past sync's decisions against its reconstructed inputs, for
  inspection — NOT a re-mutation of the live target.**
- Mechanism: for a `TimelineEntry`, reconstruct **B** (Baseline-role snapshot at `baseline_before`), **S**
  (Source-role snapshot), and **T** (Target-role snapshot before), rebuild the acceptance from the entry's
  `appliedItems`/`skippedItems`, and re-run the **frozen pure applier** (`DocumentOperationApplier`,
  INV-LT-7-MUT-01) to produce `T′`. Compare `T′`'s fingerprint to the recorded `baseline_after` / the
  Target-after snapshot.
- **Read-only / sandboxed (INV-LT-9-TL-02).** Replay produces a computed `T′` for verification and display
  only; it performs **no** write to the live target or baseline. Because the applier is pure, replay is
  safe, deterministic, and side-effect-free.
- Outcome: `ReplayResult` = `Reproduced` (computed `T′` matches the recorded outcome — a determinism/audit
  proof), `Diverged` (mismatch — a tamper/corruption signal, fail-closed surface), or `Unavailable`
  (required snapshots not retained — fail-soft, explained). Replay never fabricates missing inputs.

## 4. Undo & Rollback (reversal *plans*)

Reversal is designed here as an **immutable plan**, executed by a later additive phase — the LT-6→LT-7
pattern (decide, then execute) applied to reversal. LT-9A writes nothing.

- **Undo = revert the latest sync.** `UndoPlan` describes restoring the Target-before document (its
  snapshot) as the new target draft and moving the baseline back to `baseline_before`. Only the head entry
  is directly undoable; undoing an interior entry is Rollback.
- **Rollback = restore to an earlier point N.** `RollbackPlan` describes restoring the Target/Baseline
  document at point N and setting the baseline to that point, superseding the effect of later entries from
  the document's perspective.
- **Compensation, not erasure (INV-LT-9-TL-01).** Executing a reversal (future phase) **appends** a new
  `Undo`/`Rollback` timeline entry that records who/when/from-where/to-where and the restored reference —
  it never deletes or edits the original entries. The timeline preserves the full lineage (syncs + undos +
  rollbacks), like `git revert`, not `git reset`.
- **Snapshot-bound & fail-soft.** A reversal plan is only *offered* when the required Target/Baseline
  snapshot exists; otherwise it is `Unavailable` with an explanation (INV-LT-9-TL-03). A plan carries only
  references and provenance — never embedded content.
- **Additive execution.** When implemented, reversal runs through its **own** atomic operation (its own
  fail-closed, transactional service and its own append-only history write) — never a modification of the
  frozen sync coordinator (INV-LT-9-TL-02).

## 5. Audit

- **The timeline *is* the audit trail** — audit is reconstructed, not logged separately. For any point it
  answers *who* (`actor`), *when* (`executed_at`), *what* (applied/skipped origins + decisions), *from/to*
  which baseline (`baseline_before/after` fingerprints), and — through snapshots — the *exact documents*.
  This is the direct realisation of INV-LT-7-EXEC-02 (history sufficient to reconstruct decisions) plus
  document recall via LT-8.5.
- **Tamper-evident.** Two independent checks make the trail verifiable: baseline-chain continuity
  (INV-LT-9-TL-06) and snapshot re-hash on read (INV-LT-8.5-04). A document that does not hash to its
  recorded reference fails closed and is flagged, never shown as authentic.
- **Immutable & append-only.** Audit rests on append-only history and immutable snapshots (INV-LT-8.5-07);
  reversals extend the trail, never rewrite it.

## 6. Explainability

- **Explainability = a human/AI-readable account of *why* a sync did what it did and *what* a reversal would
  do — derived purely from retained facts.**
- **Per-item explanation** (`ItemExplanation`): from history, `{origin, source_change, decision}`; enriched,
  when snapshots exist, by re-deriving the **classification** and **conflict reason** via the frozen LT-5
  diff + LT-6 planner over reconstructed B/S/T (the same reconstruction Replay uses), plus the
  before/after node content. This yields the full `{origin, source_change, classification, conflict_reason,
  decision, before, after}` account without ever having stored it.
- **Graceful degradation (INV-LT-9-TL-04).** With snapshots ⇒ *full* explanation (content + re-derived
  classification). Without ⇒ *metadata-only* explanation (origin, source_change, decision from history).
  Explainability states which mode it is in; it never guesses missing content.
- **One-directional.** Explanations surface content only through the snapshot-consumption read plane; they
  re-inject nothing into history, plan, or provenance (the payload-safety boundary holds — mirrors
  INV-LT-8A-02). Explainability is the shared substrate the future **AI-assisted merge** consumes.

## 7. Snapshot consumption

- The unifying mechanism: Timeline, Replay, Reversal, Audit, and Explainability all obtain documents through
  a single design-level port — **`TimelineDocumentResolver`** — over the frozen LT-8.5
  `LayoutSnapshotStoreInterface`. It maps a `(TimelineEntry, role∈{BaselineBefore, BaselineAfter, Source,
  TargetBefore, TargetAfter})` to a snapshot document.
- **Read-only (INV-LT-8.5-07).** Consumers never mutate a snapshot; the store is immutable.
- **Identity-correct (INV-LT-9-TL-05).** Consumers pass `BaselineFingerprint` references (from history
  rows); the store resolves them to immutable blobs via `ContentDigest`. The timeline never treats a
  reference as a blob key or vice-versa.
- **Fail-soft, single-path (INV-LT-9-TL-03).** A missing/GC'd snapshot yields `null` → the dependent
  operation reports `Unavailable` (explained), never an alternate content path and never fabrication. A
  re-hash mismatch fails closed.
- **Availability is a first-class result.** Every timeline operation carries an explicit availability state
  (`Available` / `Unavailable(reason)`), so the UI (LT-9 UX, later) renders honest states from retained
  facts alone.

## 8. Layering & placement (design)

New subtree `src/LayoutTranslation/Timeline/` (sibling of `Execution/`, `Snapshot/`, `Review/`), design-only:

```
Timeline/
  SynchronizationTimeline, TimelineEntry, TimelineEntryKind, TimelineGap, TimelineReference
  Replay/          ReplaySandbox, ReplayResult, ReplayOutcome
  Reversal/        UndoPlan, RollbackPlan, ReversalReference   (plans only — execution is a later phase)
  Audit/           AuditTrail, AuditView                       (derived, read-only)
  Explainability/  SyncExplanation, ItemExplanation, ExplanationMode
  Consumption/     TimelineDocumentResolver                    (over the frozen LayoutSnapshotStoreInterface)
```

Dependency direction Translation → Page Builder only. It consumes LT-4/LT-5/LT-6/LT-7/LT-8.5; it is unaware
of `Review/` runtime and `ReviewUi/` presentation, and no Core file is aware of it.

## 9. Architecture regression (design-level proof obligations)

The implementation phase must prove:

1. **No runtime / coordinator / frozen-contract change** — the timeline reads history + snapshots; it does
   not modify the coordinator, the history writer, the sync path, or any frozen contract (INV-LT-9-TL-02).
2. **Read-only replay** — Replay reuses the frozen pure applier over reconstructed inputs and performs no
   live write; identical inputs → identical `T′` (determinism), matching the recorded outcome for a
   genuine entry.
3. **Append-only preserved** — reversal is designed as a plan; its future execution appends a compensating
   entry and never deletes/edits history (INV-LT-9-TL-01).
4. **Snapshot consumption is read-only, identity-correct, fail-soft** — content flows only from the LT-8.5
   store by reference→blob resolution; missing content ⇒ `Unavailable`; mismatch ⇒ fail-closed
   (INV-LT-9-TL-03/05, INV-LT-8.5-04/07/08).
5. **Metadata payload-safety intact** — the timeline metadata plane (entries, plans, audit, explanations)
   carries no embedded document content; content is retrievable only through snapshot consumption
   (INV-LT-9-TL-04, INV-LT-8.5-03).
6. **Baseline-chain continuity** — a broken chain surfaces as a `TimelineGap`, never a silent bridge
   (INV-LT-9-TL-06).
7. **No Core change; Translation → Page Builder only.**

## Non-goals (LT-9A)

No implementation; no UI (the History Browser and its interactions are a later UX phase); no reversal
*execution* (Undo/Rollback run in a later additive phase through their own atomic operation); no AI-merge
logic (this only guarantees its reconstructed inputs); no change to the coordinator, history, snapshots,
diff/planner, or any frozen contract; no Core change.

## Freeze

This architecture is the authority for the Synchronization Timeline and every operation built on it. The
timeline is a **read layer**: it may never rewrite history, mutate a snapshot, add an alternate content
path, or make any operation edit the frozen sync coordinator. Reversal is designed as a plan; its execution
is a separate, additive phase. Amendment path: a future ADR only. **Stop after architecture.**
