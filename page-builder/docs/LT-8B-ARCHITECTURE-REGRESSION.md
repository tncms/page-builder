# LT-8B — Workspace Runtime: Architecture Regression Verification

**Status: RUNTIME IMPLEMENTED (LT-8B). Framework-agnostic core only — no Filament, no Livewire, no Blade,
no Vue, no persistence change, no coordinator change, no Core change, no frozen-contract change.** This is
the regression verification the approval gate requires **before UI implementation** (LT-8C). It proves the
Workspace Runtime upholds the frozen LT-8A architecture and the eight invariants (INV-LT-8A-01…08), and
that it consumes — never duplicates — the frozen LT-5/LT-6/LT-7 pipeline.

The runtime is verified by a standalone harness
([`_lt8b_verify.php`](./_lt8b_verify.php)) that boots the runtime with no framework and no database, drives
it against the frozen contracts, and asserts every invariant: **32 checks, 32 passed, 0 failed.**

---

## 1. What LT-8B built (scope)

New subtree `src/LayoutTranslation/Review/` — pure, framework-agnostic PHP consuming the frozen pipeline.

| Scope area | Types | Role |
|---|---|---|
| **Session lifecycle** | `Session/ReviewSession`, `ReviewSessionState`, `ReviewSessionId`, `ReviewSessionSnapshot`, `ReviewSessionException`, `StaleReviewSessionException` | Orchestrates open → in-review → pause → submit → terminal; one-shot submit; guarded transitions. |
| **Projection generation** | `Tree/ReviewTreeProjector`, `ReviewTree`, `ReviewGroup`, `ReviewNode` | Pure `(plan, decisions) → tree`, grouped by review priority, deterministic. |
| **Selection model** | `Navigation/SelectionModel` | Disposable focus pointer (an origin), never a source of truth. |
| **Navigation** | `Navigation/ReviewNavigator` | Stateless next/previous/next-unreviewed over the projected tree. |
| **Preview binding** | `Preview/PreviewBinding`, `PreviewFragment`, `PreviewSide`, `PreviewAvailability`, `Contracts/PreviewProjectionInterface` | One-directional, render-only content edge. |
| **Decision binding** | `Decision/DecisionSet`, `DecisionBinding` | The single command-side store of reviewer intent; lowers to the frozen `SynchronizationAcceptanceSet`. |
| **Approval (submit)** | `Approval/ReviewApprovalService` | Assembles `ExecuteSynchronizationCommand`; invokes the frozen coordinator; maps the typed result to a terminal state. |
| **Boundary contracts** | `Contracts/ReviewInputProviderInterface`, `ReviewSessionStoreInterface`, `Subject/ReviewSubject` | Seams the runtime consumes; concrete adapters are LT-8C wiring, not built here. |

Deliberately **not** built (out of LT-8B scope): the concrete `ReviewInputProvider` / `PreviewProjection`
/ `ReviewSessionStore` adapters (infrastructure wiring), `ReviewSessionDiagnostics`, and every UI surface.

---

## 2. LT-8A invariant preservation (INV-LT-8A-01…08)

| Invariant | How the runtime preserves it | Verified by |
|---|---|---|
| **01 — computes nothing the pipeline owns** | The Review subtree imports **no** `StructuralDiffEngine`, `SynchronizationPlanner`, or three-way diff; the projector re-presents frozen plan items and reads the DecisionSet only. | static scan (§4) + "projection is deterministic", "submit hands the coordinator the same plan (never re-planned)" |
| **02 — preview content is one-directional** | `PreviewFragment`/`PreviewSide` are the only content-bearing types; they reach the preview binding only. A unique content token embedded in every fragment appears in **no** acceptance set, snapshot, or tree projection. | "acceptance set carries no preview content", "session snapshot carries no preview content", "tree projection carries no preview content" |
| **03 — only authored artifact is a frozen decision** | The DecisionSet stores only `SynchronizationDecision` enums per origin and lowers to `SynchronizationAcceptanceSet` (one `AcceptedItemDecision` per plan origin). No new decision kind exists. | "acceptance has one decision per plan origin", "accepted origins reflect the reviewer decision" |
| **04 — execution authority is the coordinator** | The session verifies nothing; it hands the carried provenance to the coordinator, which is the sole gate. Resume re-verifies provenance; a spent session cannot re-submit. | "PlanStale from the coordinator maps to Refused", "resume against drifted provenance is refused fail-closed", "a terminal session refuses a second submit" |
| **05 — fail-closed presentation** | The DecisionSet seeds every origin from the plan's fail-closed default; an untouched session encodes the safe resolution; undecided non-safe items are counted, not auto-accepted. | "conflict node seeded fail-closed KeepTarget", "untouched non-safe nodes are undecided" |
| **06 — no persistence change** | The Review subtree imports no `Illuminate`, Eloquent model, `DB`, `Schema`, or migration; the session store is an ephemeral, payload-safe port. | static scan (§4) |
| **07 — disposable, reconstructible from (plan, DecisionSet)** | Tree, selection, navigation, and preview are recomputed on demand; a session reconstructs identically from the subject's plan + a payload-safe DecisionSet snapshot. | "reconstructed tree is identical to the original", "resume … restores the review decision", "reconstruction drops decisions for vanished origins" |
| **08 — projections are read-only (CQRS)** | Reflection over `ReviewTree`/`ReviewGroup`/`ReviewNode`/`PreviewFragment`/`PreviewSide` finds **no** public `apply()`/`save()`/`accept()`/`reject()`; intent flows only through `DecisionBinding` → `DecisionSet`. | "no projection exposes apply()/save()/accept()/reject()" |

---

## 3. Frozen-contract verification

| Frozen contract (LT-5/LT-6/LT-7) | Runtime relationship | Change? |
|---|---|---|
| `SynchronizationCoordinatorInterface` / `ExecuteSynchronizationCommand` / `SynchronizationExecutionResult` / `…Outcome` | consumed verbatim; the session's only write path is the single `execute()` call | none |
| `SynchronizationAcceptanceSet` / `AcceptedItemDecision` / `SynchronizationDecision` | produced within the frozen shape (one decision per plan origin) | none |
| `SynchronizationPlan` / `…PlanItem` / `SynchronizationClassification` / `ConflictReason` | consumed, re-presented, never mutated, never re-derived | none |
| `PlanProvenance` | captured in the subject, carried verbatim into the command, re-verified on resume | none |
| `NodeChangeType` (LT-5) | read from plan items and echoed for labelling only | none |
| LT-7-FINAL-GOVERNANCE (INV-LT-7-EXEC-01/02) | upheld — provenance verified once, under lock, by the coordinator; actor threaded to history | none |

The harness asserts the coordinator receives the **same** `plan` and `provenance` object references the
subject was opened with (`$cmd->plan === $subject->plan`, `$cmd->provenance === $subject->provenance`) —
mechanical proof the Workspace never re-plans, re-diffs, or rebuilds provenance.

---

## 4. Static isolation scans

- **No diff / plan engine import.** A scan of `src/LayoutTranslation/Review/` for `StructuralDiffEngine`,
  `SynchronizationPlanner`, and `ThreeWayStructuralDiff` returns matches only in explanatory prose — no
  `use`, no instantiation. Diff = LT-5, Plan = LT-6 (INV-LT-8A-01).
- **No persistence / framework coupling.** A scan for `Illuminate\`, `DB::`, Eloquent `Model`, `Schema::`,
  and `migration` returns matches only in docblock prose and the unrelated `SelectionModel` class name (a
  runtime pointer, not an Eloquent model). The runtime touches no database and no migration
  (INV-LT-8A-06).
- **No `loadForUpdate` in preview.** The preview seam references the lock-free read path only; the string
  `loadForUpdate` appears solely in the contract's docblock stating it must **not** be used (§6, LT-8A).
- **No projection mutators.** A scan of `Review/Tree/` for `function apply|save|accept|reject` returns
  nothing (INV-LT-8A-08).

---

## 5. Verification results

```
LT-8B runtime verification: 32 passed, 0 failed
```

Covering: projection generation & determinism; fail-closed seeding; read-only projections; decision
binding; selection & navigation ordering; one-directional preview (content-token leak test);
disposability & reconstruction; resume provenance re-verification (drift → refused); orphan-decision
drop; frozen-command assembly; coordinator-as-authority; and lifecycle guards (no double submit,
`PlanStale` → Refused). `php -l` clean across all runtime files.

---

## 6. Regression verdict

The LT-8B Workspace Runtime is **consistent with the frozen LT-8A architecture and all frozen LT-0…LT-7
contracts and invariants.** It adds one plugin-owned subtree (`src/LayoutTranslation/Review/`) that is
purely presentation-and-review: it consumes the frozen coordinator and read seams, produces exactly one
payload-safe `SynchronizationAcceptanceSet` per approval, and computes none of the diff, plan, or execution
it presents. It is disposable and reconstructible from `(SynchronizationPlan, DecisionSet)` (INV-LT-8A-07)
and its projections are strictly read-only (INV-LT-8A-08). It introduces **no persistence change, no
coordinator change, no Core change, and no frozen-contract change.**

The runtime is ready for UI adapters (LT-8C: Filament/Livewire/Blade binding to `ReviewSession`,
`ReviewTree`, `ConflictPanel`/`PreviewFragment`, and `ReviewApprovalService`). **UI implementation does
not begin now — stop after the regression verification.**
