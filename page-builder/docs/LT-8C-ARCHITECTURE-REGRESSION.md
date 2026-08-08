# LT-8C — Review Workspace Presentation: Architecture Regression Verification

**Status: PRESENTATION IMPLEMENTED (LT-8C). Consumes the frozen LT-8B runtime only — no persistence
change, no coordinator change, no diff/planning change, no AI, no collaborative review, no undo/replay.**
This is the regression verification + UI-boundary proof the gate requires **before proposing LT-8D**. It
shows the presentation layer upholds the presentation governance (INV-LT-8-PRES-01/02) and preserves the
frozen LT-8A/LT-8B/LT-7 invariants.

Verified by a standalone UI-boundary harness ([`_lt8c_verify.php`](./_lt8c_verify.php)) that drives the
presentation core with fakes, no framework and no database: **21 checks, 21 passed, 0 failed.**

---

## 1. What LT-8C built (scope)

| Scope area | Type(s) | Role |
|---|---|---|
| **Framework-free presentation core** | `LayoutTranslation/ReviewUi/ReviewWorkspaceInteractor` | The single UI touchpoint to the runtime; one method per runtime operation. |
| | `LayoutTranslation/ReviewUi/ReviewWorkspaceView` | Read-only view-model builder (tree, preview, typed result). |
| **Filament entry point + Livewire shell** | `Filament/Pages/LayoutTranslationReviewPage` | Deep-link route, permission + flag gate; holds ephemeral visual state only; forwards every action to the interactor. |
| **Review tree presentation** | `resources/views/filament/pages/translation-review/_tree.blade.php` | Renders grouped nodes; each maps to `selectNode`/`toggleGroup`. |
| **Conflict panel + decision controls** | `…/_conflict-panel.blade.php` | Classification, single-path preview, and the three frozen decision controls. |
| **Session lifecycle + submit + result** | shell view + `…/_result.blade.php` | Submit through `ReviewApprovalService`; typed execution-result presentation; stale + blocked states. |

Deliberately **not** in LT-8C (deferred to **LT-8D wiring**): container bindings for
`ReviewInputProviderInterface`, `PreviewProjectionInterface`, `ReviewSessionStoreInterface`; `plugin.json`
`filament.pages` registration; and browser acceptance. Until then the page degrades to a fail-soft blocked
state (it never errors).

---

## 2. Presentation governance preservation (INV-LT-8-PRES-01/02)

| Invariant | How the presentation layer preserves it | Verified by |
|---|---|---|
| **PRES-01 — UI owns no domain state** | The shell holds only ephemeral visual props (`selectedOrigin`, `expandedGroups`, `activePanel`, `filter`). The authoritative decisions live in the runtime's session store; every request reconstructs the disposable session (INV-LT-8A-07) via the interactor. | "decide is persisted and reflected on reload", "select persists the focused origin", "submit forgets the session from the store" |
| **PRES-02 — each UI action = one runtime op** | Every interactor method performs exactly one runtime operation (`open`/`decide`/`resetDecision`/`select`/`selectNextUnreviewed`/`submit`); the shell's action methods each call exactly one interactor method. | "interactor exposes only runtime-mapped operations", "submit reaches the coordinator exactly once" |
| **PRES-02 — no duplicated decision logic / no independent acceptance / command / coordinator** | The presentation layer references no `ExecuteSynchronizationCommand`, `SynchronizationAcceptanceSet`, `AcceptedItemDecision`, or `SynchronizationCoordinatorInterface`, and calls no `->execute(`; acceptance is built only by `DecisionSet::toAcceptance()` and the command only by `ReviewApprovalService`, both inside the runtime. | static boundary scan (§4): "presentation layer constructs no command/acceptance/decision, calls no coordinator, runs no diff/planner" |
| **PRES-02 — projections rendered read-only** | View models are plain arrays built from the frozen read-only projections; the UI re-requests a fresh projection after each op rather than mutating one. | "workspace view model carries no preview content", "node view model exposes the frozen decision options" |
| **PRES-02 — single-path, fail-soft preview** | Preview is obtained only through `session->preview()` (→ `PreviewProjectionInterface`); an unavailable fragment renders a fail-soft state with no content and no alternate loading path. | "available preview carries render content", "unavailable preview renders a fail-soft state (no content)" |

---

## 3. Frozen invariant preservation (LT-8A / LT-8B / LT-7)

| Invariant | Preserved because | Verified by |
|---|---|---|
| **INV-LT-8A-01** (no diff/plan/execute duplication) | The UI re-plans nothing; `submit` hands the coordinator the runtime-built plan object unchanged. | "submit hands the coordinator the runtime-built plan (never re-planned by the UI)" + static scan (no `StructuralDiffEngine`/`SynchronizationPlanner`) |
| **INV-LT-8A-02** (preview one-directional) | Preview content reaches only the conflict-panel view model; it appears in no workspace view model, snapshot, or command. | "workspace view model carries no preview content" (unique content-token scan) |
| **INV-LT-8A-04** (coordinator is the authority) | The UI verifies nothing; a drifted-provenance action fails closed via the runtime's resume. | "a UI action against drifted provenance fails closed (stale)" |
| **INV-LT-8A-05** (fail-closed) | Untouched items keep the plan's fail-closed default; `resetDecision` restores it. | "resetDecision restores the fail-closed default" |
| **INV-LT-8A-07** (disposable, reconstructible) | The UI holds only a session id + visual state; the session is reconstructed every request from `(plan, DecisionSet snapshot)`. | "decide is persisted and reflected on reload" (round-trips through reconstruction) |
| **INV-LT-8A-08** (projections read-only) | View models are derived arrays; the UI never mutates `ReviewTree`/`ReviewNode`/`PreviewFragment`. | §4 static scan (no projection mutation) + read-only view builder |
| **INV-LT-7-EXEC-01/02** | Submit flows through `ReviewApprovalService` → coordinator, which verifies provenance once and writes history; the UI threads the actor. | "submit threads the actor", "submit reaches the coordinator exactly once" |

---

## 4. Static boundary scan

A scan of the presentation surface — `ReviewUi/*.php`, `LayoutTranslationReviewPage.php`, and every
`translation-review` Blade view — for the banned set finds **zero** occurrences of:

`new ExecuteSynchronizationCommand`, `new SynchronizationAcceptanceSet`, `new AcceptedItemDecision`,
`SynchronizationCoordinatorInterface`, `->execute(`, `StructuralDiffEngine`, `SynchronizationPlanner`,
`DecisionSet::seedFromPlan`, `new PreviewFragment`.

The interactor's public API is exactly the runtime-mapped operations (`open`, `session`, `decide`,
`resetDecision`, `select`, `selectNextUnreviewed`, `submit`, `abandon`) — no command assembly, no
acceptance construction, no coordinator handle.

---

## 5. Verification results

```
LT-8C UI-boundary verification: 21 passed, 0 failed
```

Covering: open/persist; decide & reset mapping through reconstruction; ephemeral selection; read-only
view models (content-token leak test); single-path fail-soft preview; provenance-drift fail-closed;
submit → coordinator command assembly (runtime-built plan + reviewer acceptance + actor); typed-result
presentation; store cleanup on submit; and the static boundary scan. `php -l` clean across all new PHP.

---

## 6. Regression verdict

The LT-8C presentation layer is **consistent with the presentation governance (INV-LT-8-PRES-01/02) and
all frozen LT-7/LT-8A/LT-8B contracts and invariants.** It consumes only the LT-8B runtime: it owns no
domain state, maps each UI action one-to-one to a runtime operation, renders projections read-only,
sources preview through the single `PreviewProjectionInterface` path with a fail-soft unavailable state,
and assembles no acceptance/command and calls no coordinator of its own. It introduces **no persistence
change, no coordinator change, no diff/planning change, no AI, no collaborative review, and no
undo/replay.**

**LT-8D may be proposed next** for the infrastructure wiring the presentation depends on: the concrete
`ReviewInputProvider` (LT-5 diff + LT-6 planner + provenance capture), `PreviewProjection` (lock-free
read), and an ephemeral `ReviewSessionStore`; the `plugin.json` entry-point registration; and browser
acceptance under the feature flag. **Stop after the regression verification — LT-8D is not started.**
