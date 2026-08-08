# ADR-LT-9D — Synchronization Recovery Workflow

**Status:** Accepted · **Phase:** LT-9D · **Layer:** Translation (recovery orchestration)
**Supersedes:** none · **Depends on (FROZEN):** LT-7 Execution/Coordinator, LT-8.5 Snapshots, LT-9A/9B/9C Timeline Platform

---

## Context

The Timeline Platform Foundation (LT-9A architecture, LT-9B runtime, LT-9C presentation/wiring) is complete
and **frozen**. The timeline is strictly **read-only**: it observes append-only history, replays entries
observationally, and explains them — it never executes.

Users still need a way to **recover** from a previous synchronization outcome (a divergence, a failed
execution, a broken chain, a stale baseline). Recovery is a **separate workflow that sits ABOVE the frozen
Timeline Platform**. It may *eventually* execute new synchronization operations, but it must **never** modify
historical execution, snapshots, or baselines. The append-only history model is inviolable.

## Decision

Introduce a framework-independent, pure-PHP recovery orchestration subtree at
`src/LayoutTranslation/Recovery/`. It **orchestrates**; it never executes synchronization itself. Execution is
delegated to the existing **frozen Coordinator**, which remains the sole execution authority.

### Separation of concerns

| Capability | Role | Authority |
|---|---|---|
| **Timeline Runtime** (LT-9B) | Reconstruct history, chain, projections | Read-only |
| **Replay Runtime** (LT-9B) | Re-derive a recorded outcome | Observe only |
| **Explainability Runtime** (LT-9B) | Explain a recorded outcome | Observe only |
| **Recovery Workflow** (LT-9D) | Plan recovery, orchestrate, delegate | Orchestrate only |
| **Coordinator** (LT-7) | Execute a NEW synchronization | **Execute (exclusive)** |

Replay **observes**. Recovery **orchestrates**. Execution belongs **only** to the Coordinator.

### Recovery is planning-first

Recovery **never** executes immediately. `RecoveryWorkflow::plan()` always produces a `RecoveryReport`
containing a `RecoveryPlan` (reason, eligibility, strategy, expected impact, required permissions, potential
conflicts, deterministic decision). Execution requires an explicit, confirmed `RecoveryExecutionRequest`
carrying that plan; `RecoveryWorkflow::execute()` validates it and only then delegates to the Coordinator seam.

### Recovery execution creates NEW history

```
Recovery execution → NEW synchronization execution (Coordinator)
                   → NEW append-only history row
                   → NEW baseline
                   → NEW timeline entry
```

Historical entries remain immutable. Recovery never edits history, rewrites snapshots, modifies the baseline
chain, deletes timeline entries, replaces an execution, rewrites planner output, bypasses the Coordinator, or
mutates the Timeline Runtime.

### Sources are runtime APIs, never storage

Recovery reads nothing from storage directly. The `RecoverySourceGatewayInterface` assembles an immutable
`RecoveryCandidate` by consuming ONLY the frozen runtime APIs (Timeline, Replay, Explainability, Snapshot
Resolver, Synchronization History). Because all eligibility and conflict analysis reads only that immutable
candidate, the recovery decision is **deterministic**.

### Recovery modes are strategies

`ReplayOnly` (observational), `ReExecute`, `Retry` (both delegate a NEW execution to the Coordinator),
`Manual` (human-driven), and `AiAssisted` (**reserved**, not executable this phase). Modes are strategies, not
subclasses of the timeline.

### Two seams, one deferred

- `RecoverySourceGatewayInterface` → real read-only adapter `DefaultRecoverySourceGateway` (available now).
- `RecoveryCoordinatorGatewayInterface` → **fail-closed** default `UnavailableRecoveryCoordinatorGateway`.
  The concrete adapter that lowers a confirmed recovery into an `ExecuteSynchronizationCommand` and hands it
  to the frozen Coordinator is a **later phase (LT-9E)**. Until then, execution honestly refuses and creates
  nothing — mirroring the timeline's fail-closed `NullTimelineSnapshotBackend`. The delegation contract is
  fully wired and verified via the seam.

### Permissions

New recovery permissions (above the read-only timeline permissions, which are unchanged):
`timeline.recovery.view`, `timeline.recovery.plan`, `timeline.recovery.execute`, `timeline.recovery.override`.

## Consequences

- Recovery is fully specified, wired, and verified as an orchestration layer without shipping any execution
  path — the append-only guarantees hold by construction.
- The subtree is framework-free and unit-testable against fakes; the workflow imports/instantiates the
  Coordinator **nowhere** (delegation is via its own gateway interface only).
- Actual re-execution (the command-building Coordinator adapter + admin UI) is deferred to LT-9E and is not
  begun here.

See `LT-9D-RECOVERY-WORKFLOW-FREEZE.md` for the ratified invariants and responsibility/authority matrices,
and `_lt9d_verify.php` for the framework-free verification (52 checks).
