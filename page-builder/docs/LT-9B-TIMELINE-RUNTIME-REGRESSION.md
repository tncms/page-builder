# LT-9B — Synchronization Timeline Runtime: Architecture Audit & Regression Verification

**Status: RUNTIME IMPLEMENTED (LT-9B). Framework-free domain layer only — no Filament/Livewire/Blade/Vue,
no controller/route/HTTP/API/UI, no Core/Coordinator/Planner/Diff change, no Snapshot/History/persistence
redesign.** This is the LT-9B deliverable: architecture audit, runtime component tree, responsibility
matrix, verification results, and regression verification. It implements the frozen LT-9A architecture as a
deterministic, read-only Timeline runtime — the single domain layer every future Timeline UI consumes.

Verified by a standalone harness ([`_lt9b_verify.php`](./_lt9b_verify.php)) that runs the whole runtime with
**no framework and no database**: **24 checks, 24 passed, 0 failed.**

---

## 1. Architecture audit

| Concern | Finding |
|---|---|
| **Framework independence** | The entire runtime + the frozen engines it consumes (diff, planner, operation builder, pure applier, `BaselineFingerprint`) execute under a bare PSR-4 autoloader — **no `vendor/autoload`, no Laravel**. Confirmed by the harness booting without the framework. |
| **Consumption, not modification** | Replay reuses the FROZEN `StructuralDiffEngine` → `SynchronizationPlanner` → `OperationBuilder` → `OperationApplierInterface` chain read-only; explainability reuses LT-5/LT-6. None are modified. |
| **Read ports** | History and snapshots are consumed through two framework-free read seams (`SynchronizationHistoryReaderInterface`, `TimelineSnapshotSourceInterface`); concrete adapters are deferred wiring. No history/snapshot redesign. |
| **No write surface** | Reflection over every concrete runtime type finds **no** `record`/`save`/`write`/`mutate`/`persist`/`update`/`execute`/`apply` method. |
| **No coordinator integration** | A static scan finds **no** import of the coordinator, target writer, baseline advancer, or history recorder anywhere in the subtree. |
| **Immutability** | Every entry, chain, gap, summary, statistic, result, report, and projection is an immutable value with named constructors. |
| **Determinism** | Timeline reconstruction, chain detection, replay, and explainability are pure functions of their inputs (verified: a genuine sync replays `Reproduced`; identical inputs → identical outputs). |
| **Payload-safety** | A unique content token embedded in B/S/T appears in **no** timeline, replay, explainability, or projection output. |

**Verdict:** the runtime is a faithful, framework-free realization of LT-9A that consumes the frozen pipeline
and changes nothing above or beside it.

---

## 2. Runtime component tree

New subtree `src/LayoutTranslation/Timeline/` (26 files, pure PHP):

```
Timeline/
  Contracts/
    SynchronizationHistoryReaderInterface     read seam over append-only history
    TimelineSnapshotSourceInterface           read seam over immutable snapshots
  History/
    TimelineHistoryRecord                     immutable read DTO of a history row
  Entry/
    TimelineEntryId                           entry identity (history id)
    TimelineEntry                             immutable entry projection (+ references)
  Chain/
    TimelineGap                               a baseline-continuity break
    TimelineChain                             the continuity model (gaps)
  Model/
    SynchronizationTimeline                   the immutable timeline aggregate
    TimelineSummary                           derived summary
    TimelineStatistics                        derived counts (entry/gap/success/failure/divergence)
  Builder/
    TimelineBuilder                           reconstruct + verify chain + detect gaps
  Resolution/
    TimelineDocumentRole                      BaselineBefore/After · Source · TargetBefore/After
    TimelineDocumentRef                       reference-based XOR entry-based request
    TimelineDocumentResolver                  the single document-resolution path
  Replay/
    ReplayStatus                              Reproduced · Diverged · Unavailable
    ReplayDifference                          payload-safe divergence description
    ReplayResult                              immutable typed result
    ReplayRuntime                             read-only sandbox over the frozen pure engine
  Explainability/
    ExplanationMode                           Full · MetadataOnly
    ExplainabilityNode                        per-item, metadata-only explanation
    ExplainabilityReport                      immutable report
    ExplainabilityRuntime                     reconstructive, graceful-degrading explainer
  Projection/
    TimelineProjection                        immutable ordered subset
    TimelineFilter                            immutable entry-local criteria
    TimelineSelection                         immutable id set
    TimelineProjectionBuilder                 produces new projections (all/filter/select/gapsOnly)
```

---

## 3. Responsibility matrix

| Component | Responsibility | Reads | Never |
|---|---|---|---|
| `TimelineBuilder` | Reconstruct entries; verify continuity; detect gaps | history records | mutates history |
| `SynchronizationTimeline` | The immutable timeline + on-demand summary | its entries + chain | persists anything |
| `TimelineChain` / `TimelineGap` | Report baseline continuity / a break | entries | silently bridge a gap |
| `TimelineSummary` / `TimelineStatistics` | Derived, non-persisted metrics | timeline (+ replay results) | store, mutate |
| `TimelineDocumentResolver` | The single document-resolution path | snapshot source | fabricate content; conflate reference/blob |
| `ReplayRuntime` | Recompute a past sync in a sandbox and compare | snapshots + frozen engines | write, persist, execute |
| `ReplayResult` / `ReplayDifference` | Typed, payload-safe replay verdict | — | carry content |
| `ExplainabilityRuntime` | Re-derive why a sync did what it did | snapshots + LT-5/LT-6 | carry content; re-inject upstream |
| `ExplainabilityReport` / `Node` | Metadata-only explanation | — | carry content |
| `TimelineProjection*` | Filter/select/derive immutable views | timeline | mutate a projection or the timeline |

---

## 4. Ratified invariants (LT-9B lock)

| # | Invariant | Enforced by |
|---|---|---|
| **INV-LT-9B-RT-01** | **Timeline Runtime is read-only** — it never mutates history, snapshots, coordinator state, or the baseline. | No write method exists on any type (reflection); read-only ports; static scan finds no writer/recorder/advancer import. |
| **INV-LT-9B-RT-02** | **Replay Runtime is observational** — replay never becomes execution; execution belongs exclusively to Coordinator workflows. | Replay computes a sandboxed `T′` via the pure applier and compares; it holds no writer/coordinator and persists nothing (verified: `Reproduced`/`Diverged`/`Unavailable` with no side effect). |
| **INV-LT-9B-RT-03** | **Timeline projections are immutable** — filtering, selection, and statistics always produce new projections. | Projections/filters/selections have no mutators; every builder operation returns a new instance (verified: distinct instances; source timeline unchanged). |

These compose with, and never weaken, INV-LT-9-TL-01…06 (LT-9A) and every earlier frozen invariant.

---

## 5. Verification results

```
LT-9B timeline runtime verification: 24 passed, 0 failed
```

Coverage by required category:

| Required verification | Checks |
|---|---|
| Timeline runtime | reconstruct in order · identity lookup · exposes references |
| Chain verification | continuous pair → no gap · reports continuity state |
| Gap verification | broken pair → exactly one gap · gap carries expected vs actual |
| Replay verification | genuine sync → `Reproduced` · tampered outcome → `Diverged` (names differing origin) · missing snapshots → `Unavailable` |
| Explainability verification | `Full` re-derives classification/conflict-reason via LT-5/LT-6 · decisions from history |
| Metadata-only degradation | no snapshots → `MetadataOnly` (classification dropped, history facts kept) · no content leaks anywhere |
| Projection immutability | new instance per operation · selection/gapsOnly correctness · source unchanged · filters compose immutably |
| Identity correctness | baseline/source by fingerprint reference; targets by entry id (reference ≠ blob) |
| Static architecture | framework-free, no coordinator/writer import · no write/mutate/execute method |

`php -l` clean across all 26 runtime files.

---

## 6. Regression verification

The LT-9B runtime is **consistent with the frozen LT-9A architecture and every earlier frozen contract and
invariant.** It adds one plugin-owned, framework-free subtree (`src/LayoutTranslation/Timeline/`) that:

- consumes append-only history and immutable snapshots through read-only ports;
- reconstructs a deterministic timeline, verifies baseline continuity, and surfaces gaps explicitly;
- replays past syncs observationally over the frozen pure mutation engine — never executing;
- explains syncs reconstructively via the frozen LT-5/LT-6, degrading to metadata-only without snapshots;
- exposes only immutable projections, summaries, and statistics.

It introduces **no** Filament/Livewire/Blade/Vue, controller/route/HTTP/API/UI, Core/Coordinator/Planner/Diff
change, and **no** Snapshot/History/persistence redesign. It is the single domain layer every future Timeline
UI consumes.

Two read seams (`SynchronizationHistoryReaderInterface`, `TimelineSnapshotSourceInterface`) await concrete
adapters — an Eloquent history reader and a bridge to the LT-8.5 snapshot store — which is **infrastructure
wiring, not runtime**, and out of scope for LT-9B.

**Stop after LT-9B verification. LT-9C is not started.**
