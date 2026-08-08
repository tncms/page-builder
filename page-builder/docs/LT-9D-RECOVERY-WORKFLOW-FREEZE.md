# LT-9D — Synchronization Recovery Workflow · Freeze & Invariants

**Status:** Ratified · **Layer:** Translation (recovery orchestration) · **ADR:** ADR-LT-9D
**Verification:** `php plugins/page-builder/docs/_lt9d_verify.php` → **52 passed, 0 failed**
**Regression:** LT-9B 24/0, LT-9C 27/0 (frozen Timeline Platform unchanged) · container resolves `RecoveryWorkflow`

---

## 1. Architecture audit

The recovery workflow operates **above** the frozen Timeline Platform. Timeline stays read-only; recovery is a
separate workflow that plans deterministically and delegates execution to the frozen Coordinator. No frozen
file was modified. The only edits outside the new subtree are additive: four recovery permissions and the DI
bindings in `PageBuilderServiceProvider`.

## 2. Recovery runtime tree — `src/LayoutTranslation/Recovery/`

```
Recovery/
├── Contracts/
│   ├── RecoverySourceGatewayInterface.php        read seam over runtime APIs → RecoveryCandidate
│   └── RecoveryCoordinatorGatewayInterface.php   execution seam over the FROZEN Coordinator
├── RecoveryMode.php               enum   ReplayOnly | ReExecute | Retry | Manual | AiAssisted(reserved)
├── RecoveryReason.php             enum   motivation for recovery
├── RecoveryDecision.php           enum   Recoverable | RequiresOverride | Blocked (+ deterministic derive)
├── RecoveryConflictType.php       enum   7 conflict categories (+ blocking classification)
├── RecoveryIneligibilityReason.php enum  typed refusal reasons
├── RecoveryPolicy.php             VO     declarative allowed modes / override / AI-assisted (reserved)
├── RecoveryRequest.php            VO     planning input (target, entry, mode, reason, actor, grants)
├── RecoveryCandidate.php          VO     payload-safe observation snapshot (the ONE input to analysis)
├── RecoveryEligibility.php        VO     eligible + supported modes, or typed reasons
├── RecoveryConflict.php           VO     one explicit conflict
├── RecoveryValidation.php         VO     deterministic pre-delegation validation
├── RecoveryProjection.php         VO     payload-safe read projection for presentation
├── RecoverySummary.php            VO     compact header for a plan
├── RecoveryPlan.php               VO     the confirmable artifact (+ canonical fingerprint)
├── RecoveryReport.php             VO     plan + summary + projection (plan() output)
├── RecoveryExecutionRequest.php   VO     confirmed request embedding the plan (execute() input)
├── RecoveryExecutionResult.php    VO     typed result: created | noop | rejected | failed
├── RecoveryEligibilityEvaluator.php  service  pure eligibility (candidate + policy + request)
├── RecoveryConflictResolver.php      service  pure conflict analysis (explicit; nothing inferred)
├── RecoveryWorkflow.php              service  orchestrator: plan() + execute()→delegate
├── DefaultRecoverySourceGateway.php  adapter  real read-only candidate assembly over runtime facades
└── UnavailableRecoveryCoordinatorGateway.php  adapter  FAIL-CLOSED default (command adapter = LT-9E)
```

## 3. Responsibility & authority matrix

| Component | Responsibility | May execute? | May mutate history/snapshot/baseline? |
|---|---|---|---|
| `RecoveryWorkflow` | Orchestrate plan + delegate | No (delegates) | **No** |
| `RecoveryEligibilityEvaluator` | Decide MAY-recover | No | No |
| `RecoveryConflictResolver` | Detect explicit conflicts | No | No |
| `RecoveryValidation` | Gate before delegation | No | No |
| `RecoverySourceGatewayInterface` | Assemble candidate from runtime APIs | No | No (read-only) |
| `RecoveryCoordinatorGatewayInterface` | Delegate to frozen Coordinator | Delegates only | Creates NEW execution only |
| **Coordinator** (LT-7, frozen) | Execute a NEW synchronization | **Yes (exclusive)** | Appends NEW rows only |

## 4. Eligibility model

Deterministic, typed. **Mode-independent** faults refuse every strategy: `EntryNotFound`, `FeatureDisabled`,
`CapabilityUnsupported`, `UnsupportedVersion`, `AlreadySuperseded`, `BrokenChain`, `MissingBaseline`.
**Mode-specific** faults refuse the requested strategy while still surfacing the supportable set:
`PolicyDenied` / `ModeReserved` (policy), `PermissionDenied` (grants), and feasibility (`ReplayUnavailable`
for ReplayOnly, `MissingSnapshot` for ReExecute/Retry). Identical candidate + policy + request → identical
eligibility.

## 5. Conflict model

Explicit only — nothing inferred; an unknown current-state read (null) never becomes a "differs" conflict
(the Coordinator re-verifies provenance under lock regardless).

| Conflict | Trigger | Blocking |
|---|---|---|
| `HistorySuperseded` | entry is not the chain tail | Yes |
| `SnapshotUnavailable` | required baseline/source snapshot not retained | Yes |
| `PolicyConflict` | policy no longer allows the mode | Yes |
| `PermissionConflict` | actor lacks the mode's execute permission | Yes |
| `CurrentStateDiffers` | recorded outcome no longer reproduces (replay diverged) | No (override) |
| `BaselineDiffers` | current baseline ≠ the one this entry advanced to | No (override) |
| `DocumentEvolved` | current source content-addresses to something else | No (override) |

## 6. Recovery planning model

`plan()` → observe candidate (source gateway) → evaluate eligibility → resolve conflicts →
`RecoveryDecision::derive(eligibility, conflicts)` → build `RecoveryPlan` (impact + required permissions) →
`RecoveryReport`. Decision is `Blocked` if ineligible or any blocking conflict; `RequiresOverride` if eligible
with only soft conflicts; `Recoverable` otherwise. `execute()` → `RecoveryValidation::for(request)` (confirmed,
executable non-reserved mode, decision proceeds given override, fingerprint matches) → delegate to Coordinator
seam; otherwise `rejected`.

## 7. Ratified invariants

- **INV-LT-9D-01** — Recovery never mutates history. It creates NEW history only. Expected impact declares
  `creates_new_*` for executing modes and hard-codes `modifies_history / rewrites_snapshots /
  modifies_baseline_history / deletes_timeline_entries / replaces_execution = false`. No recovery type exposes
  a history/snapshot/baseline write method (static + reflection verified).
- **INV-LT-9D-02** — Recovery planning always precedes execution. `execute()` accepts only a
  `RecoveryExecutionRequest`, which cannot be constructed without a `RecoveryPlan`; an unconfirmed, blocked, or
  tampered request is rejected before any delegation.
- **INV-LT-9D-03** — The Coordinator remains the exclusive execution authority. Recovery delegates through
  `RecoveryCoordinatorGatewayInterface` and imports/instantiates the Coordinator nowhere (verified).
- **INV-LT-9D-04** — Replay and Recovery are independent capabilities. Replay observes; Recovery orchestrates;
  execution belongs only to the Coordinator. Observational (`ReplayOnly`) and `Manual` strategies never route
  to the Coordinator.
- **INV-LT-9D-05** — Recovery decisions are deterministic. Identical inputs produce identical `RecoveryPlan`s
  (identical canonical fingerprints). No clock or randomness participates in planning.

## 8. Verification results

`_lt9d_verify.php`: **52/52** — planning, append-only impact, determinism, all eligibility reasons, all
conflict types, decision derivation, coordinator delegation, planning-precedes-execution + validation
(unconfirmed / tampered / blocked / override), replay separation, reserved-mode refusal, fail-closed default
gateway, payload safety, and the static architecture boundary (framework-free, no coordinator coupling, no
write methods).

## 9. Regression verification

- LT-9B timeline runtime: **24/0** (unchanged).
- LT-9C timeline presentation & infrastructure: **27/0** (unchanged).
- Container resolves `RecoveryWorkflow` → `DefaultRecoverySourceGateway` + `UnavailableRecoveryCoordinatorGateway`.

## 10. Frozen / deferred

LT-9D is FROZEN as an orchestration + contract foundation. The concrete `RecoveryCoordinatorGateway` adapter
(command-building over the frozen Coordinator), current-state probing for the source gateway, and the admin UI
are **LT-9E** and are **not** begun here.
