# LT-8A — Synchronization Review Workspace Architecture

**Status: ARCHITECTURE ONLY (LT-8A). No Livewire, no Filament, no Blade, no Vue, no persistence change, no
coordinator change, no Core change, no frozen-contract change.** Designs the complete Review Workspace —
the presentation-and-review layer that lets a human resolve a synchronization plan and approve its
execution. It consumes the **frozen LT-7 pipeline** verbatim and produces exactly one
`ExecuteSynchronizationCommand`; it consumes exactly one `SynchronizationExecutionResult`. It **never**
duplicates diff (LT-5), planning (LT-6), or execution (LT-7). Implementation (the concrete UI surface) is
a later phase (LT-8B) and does not begin now. **Stop after architecture.**

---

## 0. Position in the pipeline

```
LT-4 baseline ─┐
LT-5 diff  ────┤ (read-only, frozen)
LT-6 plan  ────┘
                 │  SynchronizationPlan + PlanProvenance  (immutable, payload-safe)
                 ▼
        ┌──────────────────────────────────────────────┐
        │  LT-8A  REVIEW WORKSPACE  (presentation only) │
        │                                               │
        │  Review Session → Review Tree → Conflict Panel│
        │        │                │            │        │
        │        │            Preview (content, render- │
        │        │             only, one-directional)   │
        │        ▼                                       │
        │  Decision Model  ── seeds/collects ──▶ Approval│
        └───────────────────────────────┬───────────────┘
                                         │  ExecuteSynchronizationCommand
                                         │  (target, plan, provenance, acceptance, actor)
                                         ▼
                     SynchronizationCoordinatorInterface::execute()   ← FROZEN (LT-7B3)
                                         │  SynchronizationExecutionResult (typed, payload-safe)
                                         ▼
                          Workspace renders the outcome; session closes
```

The Workspace sits **above** the frozen pipeline and **below** any concrete UI framework. Its single
downstream dependency is the frozen `SynchronizationCoordinatorInterface`. Its single upstream dependency
is an already-built `(plan, provenance)` pair produced by the frozen LT-5/LT-6 read path. Everything
between is presentation, review, and decision capture — no domain computation.

### The decisive constraint: the payload-safety boundary meets a human eye

LT-7 is deliberately **payload-safe** — plans, provenance, acceptance, results, and history carry
*origins + enums + fingerprints only, never node content* (LT-7-ARCHITECTURE-REGRESSION §4). But a human
reviewer **must see content** to decide a `Conflict` or `ReviewRequired` item. LT-8A resolves this tension
with a strict, one-directional rule (formalised as **INV-LT-8A-02**):

> Node content enters the Workspace **only** to be rendered for a human. It never re-enters the plan, the
> acceptance set, the command, the coordinator, or history. The pipeline stays payload-safe; only the
> *presentation edge* ever touches content, and content flows **into** the screen, never **back** into the
> pipeline.

This is the architecture's central invariant. Everything below is built to keep it mechanically true.

---

## 1. Workspace invariants (LT-8A lock)

These bind LT-8B and every later review surface. They compose with — and never weaken — the frozen
LT-0-ADR-001 invariants, LT-6 fail-closed policy, and LT-7-FINAL-GOVERNANCE.

| # | Invariant |
|---|---|
| **INV-LT-8A-01** | **The Workspace computes nothing the pipeline owns.** It never derives a classification, never re-plans, never re-diffs, never mutates a target. Diff = LT-5, Plan = LT-6, Execution = LT-7. The Workspace only *presents* them and *collects a decision*. |
| **INV-LT-8A-02** | **Preview content is presentation-only and one-directional.** Node content enters the Workspace solely to render; it never re-enters the plan, acceptance, command, coordinator, or history. No decision, count, or fingerprint is ever derived from rendered content. |
| **INV-LT-8A-03** | **The only authored artifact is a per-origin decision drawn from the frozen `SynchronizationDecision` set.** The Workspace invents no new decision kind and emits exactly one `SynchronizationAcceptanceSet` per approval. |
| **INV-LT-8A-04** | **Execution authority is the coordinator, not the session.** A session is advisory. Provenance verified exactly once under the coordinator's lock is the sole gate. A submitted session whose inputs drifted is refused (`PlanStale`/`ConcurrentConflict`), never force-applied. |
| **INV-LT-8A-05** | **Fail-closed presentation.** An undecided `ReviewRequired`/`Conflict` item carries the plan's fail-closed default (`KeepTarget`). The Workspace never silently auto-accepts a non-safe item on the reviewer's behalf. |
| **INV-LT-8A-06** | **No persistence change.** LT-8A introduces no migration, table, column, or schema. The review-session store is an ephemeral port; its durable backing (if ever wanted) is a later, separately-governed decision. |
| **INV-LT-8A-07** | **The Workspace is disposable and never a source of truth.** A session is always reconstructible from exactly the `SynchronizationPlan` and the `DecisionSet`; no irreplaceable in-memory state may exist. Ratified in [`LT-8A-FINAL-GOVERNANCE.md`](./LT-8A-FINAL-GOVERNANCE.md). |
| **INV-LT-8A-08** | **Every Review projection is read-only (CQRS).** Projection objects never expose `apply()`/`save()`/`accept()`/`reject()`; reviewer intent is stored exclusively in the working `DecisionSet`. Ratified in [`LT-8A-FINAL-GOVERNANCE.md`](./LT-8A-FINAL-GOVERNANCE.md). |

---

## 2. Review Session lifecycle

A **Review Session** is the presentation aggregate for one review of one target. It is a *value-anchored*
object: it is bound at open time to an immutable `ReviewSubject` and accumulates a mutable working
`DecisionSet` until it is submitted, at which point the decisions freeze.

### 2.1 The bound subject (immutable)

```
ReviewSubject                       (immutable, payload-safe)
  ├─ PageTranslationKey  target     (identity)
  ├─ SynchronizationPlan plan       (frozen LT-6 value, verbatim)
  └─ PlanProvenance      provenance (frozen LT-7 value — fingerprints of the exact B/S/T planned from)
```

The subject is produced by the **`ReviewInputProvider`** port (§9), which delegates to the frozen LT-5
diff + LT-6 planner and captures the `PlanProvenance` (the fingerprints of the exact B, S, T it planned
from). The provider adds **no** new classification or planning logic — it composes existing frozen read
services and binds the plan to its provenance. The Workspace treats the subject as read-only truth.

### 2.2 States

```
        (open)                     (decide…)             (submit)
  ┌────────────┐  provenance ok  ┌──────────┐          ┌───────────┐
  │  Opening   ├────────────────▶│ InReview │─────────▶│ Submitting│
  └─────┬──────┘                 └────┬─────┘          └─────┬─────┘
        │ blocked                     │ resume/pause         │
        │ (diagnostics)               ▼                      │ coordinator result
        ▼                        ┌──────────┐                ▼
  ┌───────────┐                  │  Paused  │        ┌───────────────┐
  │  Blocked  │                  └────┬─────┘        │  Terminal:    │
  │ (no open) │◀──── stale ──────────┘              │  Executed     │
  └───────────┘   (provenance drift)                 │  NoOp         │
                                                      │  Refused      │
                                                      │  Abandoned    │
                                                      └───────────────┘
```

| State | Meaning | Allowed transitions |
|---|---|---|
| **Opening** | Diagnostics consulted; subject being provisioned. | → `InReview` (would-execute true) · → `Blocked` (feature off / unbound / no baseline / no source) |
| **Blocked** | The target cannot be reviewed right now; the Workspace shows *why* and offers no decision surface. | → `Opening` (re-check) |
| **InReview** | Subject bound; reviewer is authoring decisions in the working `DecisionSet`. | → `Paused` · → `Submitting` · → `Abandoned` |
| **Paused** | Session suspended; decisions retained in the session store (§7). | → `InReview` (resume, provenance re-verified) · → `Blocked`/`Refused` (stale) |
| **Submitting** | Decisions frozen; `ExecuteSynchronizationCommand` assembled; coordinator invoked. | → terminal per `SynchronizationExecutionResult` |
| **Executed** | Coordinator returned `Executed`. Baseline advanced, one history row written. Session is spent. | (terminal) |
| **NoOp** | Coordinator returned `NoOp` (nothing accepted / empty plan). No write. | (terminal) |
| **Refused** | Coordinator returned any fail-closed outcome (`PlanStale`, `AcceptanceInvalid`, `CorrespondenceFault`, `ConcurrentConflict`, …). Nothing mutated. | (terminal; a fresh session may re-open) |
| **Abandoned** | Reviewer discarded the session. | (terminal) |

**Lifecycle rules.**

- A session **cannot** enter `Submitting` unless the diagnostics-derived `would_execute` gate held at open
  time **and** the pre-submit presentation gate (§6.3) is satisfied. Both are advisory — the coordinator
  re-checks authoritatively.
- `Submitting` is **one-shot**. A session emits at most one `ExecuteSynchronizationCommand`. On any
  terminal outcome the session is spent; re-review requires a fresh session (fresh subject, fresh
  provenance).
- The session **holds no lock** and **writes nothing** at any state except through the coordinator during
  `Submitting`. Pausing/resuming touches only the ephemeral session store.

---

## 3. Review Tree

The **Review Tree** is a read-only, hierarchical projection of the plan for navigation and scanning. It
is **derived from the plan and the origin structure** — it is *not* a diff and *not* a re-plan
(INV-LT-8A-01).

### 3.1 Shape

```
ReviewTree
  └─ ReviewGroup (by classification: Conflict → ReviewRequired → Safe)   ← reviewer-priority order
       └─ ReviewNode (one per SynchronizationPlanItem, keyed by origin)
            ├─ origin            (string, identity — from the plan item)
            ├─ sourceChange      (NodeChangeType — from the plan item)
            ├─ classification    (SynchronizationClassification — from the plan item)
            ├─ conflictReason    (?ConflictReason — from the plan item)
            ├─ effectiveDecision (SynchronizationDecision — from the DecisionSet, §5)
            └─ decided           (bool — reviewer touched it, or still on the default)
```

- **One `ReviewNode` per plan item.** The tree never adds, merges, or splits items — it re-presents them.
- **Grouping is presentation.** The default grouping surfaces `Conflict` first, then `ReviewRequired`,
  then `Safe`, because that is review priority. Optional secondary organisation by the origin's structural
  path (nesting nodes under their container origin) is a *display* arrangement only; it derives the
  hierarchy from origin identity, never from content and never from a diff.
- **Deterministic order.** Within a group, nodes follow the plan's own deterministic order (LT-6 guarantees
  identical inputs → identical plan order). The tree adds no nondeterminism.
- **Payload-safe.** Every `ReviewNode` field is an origin or enum. The tree carries **no content**;
  content is fetched lazily and only by the Conflict Panel/Preview when a node is opened (§4, §6).

### 3.2 What the tree is not

It is not a document tree of `S` or `T`; it is a tree of *plan items*. It never reads a payload to build
itself, so a plan with zero content access still yields a complete tree. Safe items are shown for
transparency (the reviewer sees what will be auto-applied) but need no per-item decision.

---

## 4. Conflict Panel

The **Conflict Panel** is the focused, single-item review surface. It is opened for one `ReviewNode`
(typically `Conflict` or `ReviewRequired`; a `Safe` node may be inspected read-only). It is the **only**
surface that requests content, and it does so through the Preview model (§5).

### 4.1 Composition

```
ConflictPanel(origin)
  ├─ Header:   classification badge + conflictReason label   (from the plan item — no computation)
  ├─ Preview:  PreviewFragment(origin)                       (baseline / source / target renders, §5)
  ├─ Decision: DecisionControl(origin)                        (the three frozen SynchronizationDecision
  │              options, seeded with the plan default)       choices, §5)
  └─ Rationale (optional, non-persisted): reviewer note held only in the session store, never sent to the
               coordinator, never stored in history (payload boundary).
```

- The header **labels** the frozen classification and reason; it does not decide them.
- The Preview shows the human *why* the plan classified this item (source text changed after translation,
  source removed a target-modified node, structural conflict, …). The panel renders a **human-facing
  visual diff** of the already-fetched `S`/`T`/`B` fragments — a *display* concern, produced by a
  presentation diff-for-reading utility. This is emphatically **not** the LT-5 `StructuralDiffEngine`: it
  computes no `NodeChangeType`, feeds nothing back into the plan, and its output is pixels, not policy
  (INV-LT-8A-01, INV-LT-8A-02).
- The Decision control writes one `SynchronizationDecision` for this origin into the working `DecisionSet`
  (§5). It offers exactly `AcceptSource` / `KeepTarget` / `Ignore` — the frozen set, nothing invented.

### 4.2 Guarantees

- **No mutation.** Opening, scrolling, or closing the panel changes nothing but the in-session working
  decision the reviewer explicitly sets.
- **Content never leaves the panel.** The `PreviewFragment` it renders is not copied into the tree, the
  decision, the command, or any log (INV-LT-8A-02).

---

## 5. Decision Model

The Decision Model is where the Workspace's *only* authored output lives.

### 5.1 Working decisions (in-session, mutable)

```
DecisionSet                                   (working, mutable during InReview)
  └─ map<origin, SynchronizationDecision>      keyed by plan-item origin
        seeded from SynchronizationPlanItem.defaultDecision for every item
```

- **Seeding is fail-closed.** Every origin starts on the plan's `defaultDecision` — `AcceptSource` for
  `Safe`, `KeepTarget` for `ReviewRequired`/`Conflict` (LT-6). An untouched session therefore already
  encodes the safe, non-destructive default (INV-LT-8A-05).
- **Editing is per origin.** The reviewer overrides an origin to any of the three frozen decisions. The
  model records *which origins the reviewer explicitly touched* (`decided`) so the UI can distinguish
  "reviewed" from "still default" — this flag is presentation metadata, never sent downstream.
- **Bulk operations are convenience over the same primitive.** "Accept all safe", "keep all conflicts",
  "accept this subtree" are batch writes of per-origin decisions; they create no new decision semantics.

### 5.2 Frozen acceptance (at submit)

At submit the working `DecisionSet` is **frozen** and lowered to the frozen contract:

```
DecisionSet  ──lower──▶  SynchronizationAcceptanceSet
                           = [ AcceptedItemDecision(origin, decision) for each plan-item origin ]
```

- **One decision per plan-item origin** — matching `SynchronizationAcceptanceSet`'s duplicate-origin
  rejection and the coordinator's `AcceptanceValidator` (every decided origin must exist in the plan; the
  plan and acceptance origins must correspond).
- The lowering is **total and deterministic**: because every origin was seeded, the acceptance set covers
  the whole plan with no gaps and no extras. The Workspace does not decide *policy*; it records the
  reviewer's resolution of the plan's DEFAULT decisions into RESOLVED ones — exactly the role
  `AcceptedItemDecision` documents.
- **Payload-safe by construction:** origins + `SynchronizationDecision` enum only. No content, no note, no
  preview fragment crosses into the acceptance set.

---

## 6. Preview model

The Preview model is the *only* place content crosses the payload boundary, and it does so under
INV-LT-8A-02.

### 6.1 Port and value

```php
interface PreviewProjectionInterface
{
    // Lock-free, read-only. Returns the renderable fragments for one origin, or a not-available marker.
    public function fragmentFor(PageTranslationKey $target, string $origin): PreviewFragment;
}

final class PreviewFragment            // presentation value — content-BEARING, render-only
{
    // Renderable projections of the three sides for this origin. Presentation strings/structures only.
    // Never re-enters the pipeline. Never persisted. Never logged.
    public readonly ?PreviewSide $baseline;   // B side (what the last sync established)
    public readonly ?PreviewSide $source;     // S side (what the source now says)
    public readonly ?PreviewSide $target;     // T side (the current translation)
    public readonly NodeChangeType $change;   // echoed from the plan item — for labelling only
    public readonly PreviewAvailability $availability; // Available | SourceGone | TargetGone | NotBound
}
```

### 6.2 Rules

- **Lock-free read.** The projection reads through the same *non-locking* read seams the Diagnostics uses
  (`load()`, the source reader) — **never** the execution loader's `loadForUpdate()`. Preview never
  contends with execution and never begins a transaction.
- **One-directional (INV-LT-8A-02).** `PreviewFragment` is consumed by the Conflict Panel and discarded.
  It is never assigned into a `ReviewNode`, a `DecisionSet`, an `AcceptedItemDecision`, the command, or the
  session store's persisted portion.
- **Presentation diff, not structural diff.** Any side-by-side or inline highlight the panel shows is a
  *reading aid* computed over already-fetched fragments by a presentation utility. It produces no
  `NodeChangeType`, influences no decision programmatically, and is invisible to the pipeline. The
  authoritative change classification remains the plan's (`sourceChange`, `classification`,
  `conflictReason`).
- **Fail-soft.** If a side is unavailable (`SourceGone`/`TargetGone`/`NotBound`), the fragment says so and
  the panel renders a degraded-but-honest view. Preview never repairs, never fabricates a side, and never
  blocks the plan — a missing preview does not change the plan or the decision defaults.
- **Staleness-aware, not staleness-authoritative.** Preview may read `S`/`T` that has drifted since the
  plan was built. It is allowed to be slightly out of date; correctness is not its job. The single
  authoritative staleness verdict is the coordinator's provenance check at submit (§8). Preview is a
  *convenience*, provenance is *truth*.

---

## 7. Approval workflow

Approval is the bridge from the Workspace to the frozen coordinator.

### 7.1 The submit path

```
1. Freeze the working DecisionSet.
2. Lower → SynchronizationAcceptanceSet.                          (§5.2)
3. Assemble ExecuteSynchronizationCommand(
       target      = subject.target,
       plan        = subject.plan,          ← verbatim, never re-planned
       provenance  = subject.provenance,    ← captured at open, verbatim
       acceptance  = lowered acceptance,
       actor       = current actor identity)
4. coordinator.execute(command)  ─────────────▶  SynchronizationExecutionResult   (FROZEN call)
5. Map result.outcome → terminal session state (§2.2) and render it.
```

The Workspace **assembles and hands off**; it does not implement any step the coordinator owns
(eligibility, loading, provenance verify, lowering to operations, mutation, persistence, baseline advance,
history, transaction). Those are LT-7 and stay LT-7.

### 7.2 Authority

- **Permission gate.** Opening a review requires the review permission; approving requires the execute
  permission (resolved through the existing `cms_can` seam, consistent with the plugin's other admin
  surfaces). This is an access decision, not a domain decision.
- **Actor capture.** The approving actor is threaded into the command's optional `actor` for the
  append-only history row (INV-LT-7-EXEC-02). The Workspace supplies identity only — a string — never
  content.

### 7.3 Pre-submit presentation gate (advisory, fail-closed)

Before enabling submit, the Workspace may require that **every non-safe item has been explicitly
reviewed** (`decided = true`), surfacing "N conflicts still on their default" rather than silently
shipping defaults. This is a *presentation* guard that mirrors LT-6/LT-7 fail-closed policy; it never
changes a decision. Because seeding is already fail-closed, even a bypassed gate can only ever submit the
safe defaults — the gate improves *reviewer awareness*, it does not create safety (safety is the seed +
the coordinator).

### 7.4 Outcome mapping

| `SynchronizationExecutionOutcome` | Session terminal | Reviewer-facing meaning |
|---|---|---|
| `Executed` | Executed | Applied; baseline advanced; one history row written. Show applied/skipped counts + new fingerprint. |
| `NoOp` | NoOp | Nothing to apply (empty/all-kept). No write. |
| `PlanStale` / `ConcurrentConflict` | Refused | Inputs drifted since review opened — offer "re-review" (open a fresh session). |
| `AcceptanceInvalid` | Refused | The submitted decisions did not match the plan — a Workspace assembly bug or a raced plan; re-open. |
| `CorrespondenceFault` / `DocumentInvalid` / `IntegrityFault` | Refused | The accepted change could not be safely applied to `T`; target untouched. |
| `FeatureDisabled` / `CapabilityUnsupported` / `TargetNotBound` / `BaselineMissing` / `SourceUnavailable` / `TargetUnavailable` | Refused/Blocked | Target no longer eligible; drop to the Blocked banner. |
| `PersistenceFailure` | Refused | Transient/infra; nothing mutated; safe to retry with a fresh session. |

Every non-success outcome leaves the target byte-identical (LT-7 guarantees it); the Workspace's job is
only to *explain* the outcome, never to retry-mutate or reconcile by hand.

---

## 8. Workspace resume

A review may span interruptions. Resume reconstructs a session **without** persisting anything the
pipeline owns and **without** trusting stale decisions blindly.

### 8.1 What is retained vs. reconstructed

| Piece | On pause | On resume |
|---|---|---|
| `target` | held (identity) | reused |
| `plan` | **not** stored | **re-derived** from the provider (deterministic — LT-6 guarantees identical plan for identical inputs) |
| `provenance` | held (fingerprints only, payload-safe) | re-verified against current B/S/T fingerprints |
| working `DecisionSet` | held in the session store, keyed by origin | re-attached by origin to the freshly derived plan |

### 8.2 Resume algorithm

```
1. Re-open the subject via ReviewInputProvider(target)  → (plan_now, provenance_now).
2. Compare the retained provenance to provenance_now:
     • unchanged  → the plan is the same plan; re-attach decisions by origin.
     • drifted    → the session is STALE. Discard the working decisions as unsafe;
                    present the fresh plan for re-review (or drop to Blocked/Refused).
3. Re-attach decisions:
     • origin still in the plan   → restore the reviewer's decision.
     • origin no longer present   → drop it (fail-closed; a decision for a vanished item is meaningless).
     • new origin in the plan     → seed with its fail-closed default (INV-LT-8A-05).
4. Enter InReview.
```

- **Determinism makes resume safe.** Because LT-6 produces an identical plan from identical inputs, a
  non-drifted resume restores exactly the plan the reviewer was working, and decisions re-attach by origin
  with no ambiguity.
- **Drift invalidates decisions, not the reviewer.** If any of B/S/T moved, the retained decisions were
  made against a plan that no longer describes reality; they are discarded rather than silently carried
  onto a different plan. This is the same fail-closed stance the coordinator takes with `PlanStale`.
- **No persistence change (INV-LT-8A-06).** The session store is an ephemeral port. Its default backing
  is process/cache-scoped and payload-safe (it holds origins, decisions, provenance fingerprints, and
  optional non-persisted reviewer notes — never content, never `S`/`T`/`B` documents). Whether a durable
  backing is ever added is a separate, later, governed decision; LT-8A adds no table.

### 8.3 Session store port

```php
interface ReviewSessionStoreInterface
{
    public function put(ReviewSessionSnapshot $snapshot): void;   // payload-safe snapshot only
    public function get(ReviewSessionId $id): ?ReviewSessionSnapshot;
    public function forget(ReviewSessionId $id): void;
}
```

`ReviewSessionSnapshot` carries: session id, `PageTranslationKey`, `PlanProvenance` (fingerprints),
`DecisionSet` (origin → decision), and the `decided` flags. It is **payload-safe** — it never stores a
plan payload, a preview fragment, or node content. A snapshot is enough to *reconstruct* a session
(re-derive the plan, re-verify provenance, re-attach decisions), never enough to *reconstruct content*.

---

## 9. Concurrency model

The Workspace is **provenance-anchored optimistic review**. It holds no lock; the coordinator is the only
authority.

- **Sessions never lock.** Reviewing is read-only and lock-free. Any number of reviewers may open sessions
  on the same or different targets concurrently. Opening a review contends with nothing.
- **The coordinator arbitrates writes (frozen LT-7 §11).** On submit, the coordinator locks the target's
  baseline row `FOR UPDATE`, verifies the provenance **exactly once**, and applies. Two sessions racing on
  one target serialize there: the first to submit advances the baseline; the second's provenance now
  drifts, and its submit returns `PlanStale`/`ConcurrentConflict`. The Workspace maps that to Refused and
  offers re-review. **Never a lost update, never an interleaved write** — the Workspace inherits this from
  LT-7 rather than re-implementing it.
- **Cross-target isolation.** Sessions on different targets never contend (distinct lock rows, shared but
  read-only source). Reviewing many locales of one source in parallel is safe.
- **The session store is advisory, not authoritative.** A stored snapshot can go stale between pause and
  resume; §8 re-verifies provenance on resume and the coordinator re-verifies on submit. Two devices
  editing the same session id is a last-writer-wins on the *snapshot*, but can never produce an unsafe
  execution because the authoritative gate is always the single under-lock provenance check.
- **One command per session (§2.2).** A session emits at most one `ExecuteSynchronizationCommand`; there
  is no partial submit, no incremental apply, and therefore no mid-review write to reconcile. This mirrors
  INV-LT-7-EXEC-01 (provenance verified once, mutation atomic).

---

## 10. Diagnostics

The Workspace has two read-only diagnostic surfaces; neither mutates or decides anything.

### 10.1 Consumed: execution diagnostics (frozen)

`SynchronizationExecutionDiagnostics::report(PageTranslationKey)` (frozen LT-7B3) drives the **open-time
banner** and the `would_execute` gate: feature flag, capability, binding presence, baseline presence,
source/target availability, and the `source_ahead_of_baseline` signal. The Workspace uses it to decide
whether a session may leave `Opening` for `InReview` or must go `Blocked`, and to render *why* a target
cannot be synced. It is consumed verbatim — the Workspace adds no execution diagnosis of its own.

### 10.2 Owned: review-session diagnostics (presentation)

A thin, read-only `ReviewSessionDiagnostics` reports the **session's own presentation state** for support
and UI:

- session state (Opening/Blocked/InReview/Paused/Submitting/terminal),
- plan summary echoed from `SynchronizationPlan::summary()` (safe / review / conflict counts),
- decided vs. undecided counts (from the `DecisionSet`),
- provenance-freshness hint (retained provenance vs. current fingerprints — the *same* signal the
  coordinator will check, surfaced early and non-authoritatively),
- preview availability per opened origin.

It emits **fingerprints, enums, and counts only** — no content (payload boundary). It mutates nothing,
repairs nothing, and decides nothing; it is a mirror, not a controller. Where its freshness hint and the
coordinator's verdict could differ (a race between hint and submit), the coordinator's under-lock verdict
always wins.

---

## 11. Layering & placement

New subtree `src/LayoutTranslation/Review/` (sibling of `Execution/`, `Plan/`, `Diff/`, `Baseline/`):

```
Review/
  Contracts/    ReviewInputProviderInterface, PreviewProjectionInterface,
                ReviewSessionStoreInterface
                (consumes frozen: SynchronizationCoordinatorInterface,
                 SynchronizationExecutionDiagnostics; LT-5 diff + LT-6 planner via the provider)
  Subject/      ReviewSubject, ReviewInputProvider (delegates to frozen LT-5/LT-6; captures PlanProvenance)
  Session/      ReviewSession, ReviewSessionState, ReviewSessionId, ReviewSessionSnapshot,
                ReviewSessionStore (ephemeral default impl)
  Tree/         ReviewTree, ReviewGroup, ReviewNode
  Preview/      PreviewFragment, PreviewSide, PreviewAvailability, PreviewProjection (lock-free reader),
                PresentationDiff (reading aid — NOT StructuralDiffEngine)
  Decision/     DecisionSet  (lowers to the frozen SynchronizationAcceptanceSet)
  Approval/     ReviewApprovalService (assembles ExecuteSynchronizationCommand; invokes the coordinator;
                maps SynchronizationExecutionResult → terminal state)
  Diagnostics/  ReviewSessionDiagnostics
```

- **Dependency direction:** Translation → Page Builder only. The Review layer depends **downward** on the
  frozen LT-7 contracts and the frozen LT-1…LT-6 read seams. **No Core, no Page Builder core, and no LT-7
  file is aware of the Review layer.** Coordinator, applier, loader, and history are consumed, never
  modified (INV-LT-8A-04, and the LT-7 freeze).
- **UI is out of scope.** Livewire, Filament, Blade, and Vue are the LT-8B presentation *adapters* that
  will drive `ReviewSession`, render `ReviewTree`/`ConflictPanel`/`PreviewFragment`, and call
  `ReviewApprovalService`. LT-8A defines the framework-agnostic core those adapters bind to and nothing
  more.

---

## 12. Architecture regression

This is the design-level review. It defines the proof obligations the LT-8B implementation must satisfy
with tests before it may write UI.

### 12.1 No duplication of diff / planning / execution (INV-LT-8A-01)

| Owned by | The Review layer's relationship | Proof obligation |
|---|---|---|
| **Diff (LT-5)** | never called at review time except through the frozen LT-6 planner inside `ReviewInputProvider`; the Conflict Panel's reading-aid diff produces no `NodeChangeType`. | A test asserts the Review layer imports/executes no `StructuralDiffEngine` to classify; classifications shown equal the plan's, byte-for-byte. |
| **Plan (LT-6)** | consumed verbatim; re-derived deterministically on resume; never edited. | A test asserts `subject.plan` is passed to the command unmodified; and that resume re-derivation yields an identical plan for identical inputs. |
| **Execution (LT-7)** | consumed only via `SynchronizationCoordinatorInterface::execute`; the Review layer performs no load/verify/mutate/persist/advance/history. | A test asserts the only write path is the single coordinator call; the Review layer never writes `pb_layouts`, baseline, or history directly. |

### 12.2 Payload-safety across the review boundary (INV-LT-8A-02)

- The `ReviewSubject`, `ReviewTree`, `ReviewNode`, `DecisionSet`, `ReviewSessionSnapshot`,
  `SynchronizationAcceptanceSet` produced, and `ReviewSessionDiagnostics` output contain **no** node
  content — origins, enums, counts, and fingerprints only. Proven with unique content tokens embedded in
  `S`/`T` (the LT-5/LT-6/LT-7 discipline), asserting the tokens never appear outside a `PreviewFragment`.
- `PreviewFragment` (the one content-bearing value) is asserted to reach **only** the Conflict Panel
  renderer — never a `ReviewNode`, `DecisionSet`, command, coordinator, history, or session snapshot.

### 12.3 Frozen-contract preservation

| Frozen contract | Review relationship | Change? |
|---|---|---|
| `SynchronizationCoordinatorInterface` / `ExecuteSynchronizationCommand` / `…Result` | consumed verbatim | none |
| `SynchronizationAcceptanceSet` / `AcceptedItemDecision` / `SynchronizationDecision` | produced within the frozen shape; one decision per plan origin | none |
| `SynchronizationPlan` / `…PlanItem` / classification / conflict-reason enums | consumed, never mutated | none |
| `PlanProvenance` | captured at open, carried verbatim, re-verified on resume/submit | none |
| `SynchronizationExecutionDiagnostics` | consumed read-only | none |
| LT-7-FINAL-GOVERNANCE (INV-LT-7-EXEC-01/02) | upheld — one provenance check under lock; actor threaded to history | none |

### 12.4 Fail-closed & authority (INV-LT-8A-04/05)

- A test asserts an untouched session lowers to an acceptance set of the plan's fail-closed defaults
  (every non-safe item `KeepTarget`), so an approve-without-review can never auto-overwrite translated
  content or auto-remove a target-modified node (INV-LT-04/INV-LT-03 preserved through the Workspace).
- A test asserts a drifted-provenance submit returns `PlanStale`/`ConcurrentConflict` and leaves the
  target byte-identical — the session cannot force an apply over the coordinator's verdict.

### 12.5 No persistence / no Core / runtime isolation (INV-LT-8A-06)

- **No migration, table, column, or schema** is added by LT-8A. The session store is an ephemeral,
  payload-safe port; a test asserts no new DB write path exists in the Review layer outside the coordinator
  call.
- **No Core file changed**, no new Core port. Dependency direction is Translation → Page Builder only.
- **Flag-gated and dormant:** with `page-builder.translation.enabled` off, diagnostics report
  `feature_enabled=false`, sessions cannot open, and no single-language runtime path changes (INV-LT-07).

### 12.6 Regression verdict

The LT-8A architecture is **consistent with all frozen LT-0…LT-7 contracts and invariants.** It adds one
new plugin-owned subtree (`src/LayoutTranslation/Review/`) that is *purely presentation and review*: it
consumes the frozen coordinator and read seams, produces exactly one payload-safe
`SynchronizationAcceptanceSet` per approval, and computes none of the diff, plan, or execution it presents.
It introduces **no persistence change, no coordinator change, no Core change, and no frozen-contract
change.** Implementation (the LT-8B UI adapters) may proceed against these proof obligations in a later
phase — **not now.**

---

## Non-goals (LT-8A)

No UI implementation (Livewire / Filament / Blade / Vue — that is LT-8B); no review *logic* beyond
decision capture and assembly (this is architecture only); no new decision kinds; no auto-resolution of
conflicts; no undo/revert surface (LT-9); no durable review-session persistence; no schema migration; no
coordinator, pipeline, or Core change; no frozen-contract change.

## Freeze

With this architecture ratified, **LT-8A is the authority for LT-8B and any later review surface.** The
Workspace consumes the frozen LT-7 pipeline and may never duplicate its diff, planning, or execution, nor
weaken the eight LT-8A invariants (INV-LT-8A-01…08; 07 and 08 ratified in
[`LT-8A-FINAL-GOVERNANCE.md`](./LT-8A-FINAL-GOVERNANCE.md)). Amendment path: a future ADR only. **Stop
after architecture.**
