# LT-8D — Workspace Runtime Wiring: Architecture Regression Verification

**Status: WIRING IMPLEMENTED (LT-8D). Additive + flag-gated. No Workspace Runtime change, no Presentation
change, no Coordinator change, no Diff/Planner change, no persistence redesign.** This is the regression
verification the gate requires **before proposing LT-9**. It binds the three LT-8 ports the frozen LT-8B
runtime + LT-8C presentation consume, registers the entry point, and verifies the wiring end-to-end.

Verified by a standalone harness ([`_lt8d_verify.php`](./_lt8d_verify.php), **18/18**) plus a real
container-resolution + feature-flag boot check (all bindings resolve; flag-off ⇒ page inaccessible).

---

## 1. The baseline-document decision (approved)

Building a review subject requires the frozen LT-5 three-way diff, which needs the baseline **document**
B. LT-4 persists only B's **content-addressed fingerprint** (Decision B2) — no payload — and the source
row holds current S, not the last-synced snapshot. Sourcing a real B requires persisting the baseline
payload, which collides with this phase's "no persistence redesign" rule.

**Approved resolution: seam now, defer persistence.** LT-8D wires the provider against a
`BaselineDocumentSourceInterface` seam with a fail-closed default (`UnavailableBaselineDocumentSource`
returns null). The workspace degrades to a fail-soft "unavailable" state until a later, separately-governed
persistence phase supplies baseline documents. No runtime, presentation, coordinator, diff/planner, or
persistence change is made here.

---

## 2. What LT-8D built (scope)

| Scope area | Type | Notes |
|---|---|---|
| **ReviewInputProvider** | `ReviewInfrastructure/PlanningReviewInputProvider` | Reads state lock-free (LT-7 loader `load()`), obtains B via the seam, **delegates** to the frozen LT-5 `StructuralDiffEngine` + LT-6 `SynchronizationPlanner`, captures coordinator-consistent `PlanProvenance`. Fails closed via `ReviewSubjectUnavailableException`. |
| **Baseline seam** | `ReviewInfrastructure/BaselineDocumentSourceInterface` + `UnavailableBaselineDocumentSource` | The single, explicit baseline-document seam; fail-closed default. |
| **PreviewProjection** | `ReviewInfrastructure/LayoutPreviewProjection` | Reads S/T **lock-free**, locates nodes by correspondence origin, renders read-only; fail-soft on unbound/missing baseline; strips the correspondence key. |
| **ReviewSessionStore** | `ReviewInfrastructure/CacheReviewSessionStore` | Ephemeral, cache-backed (TTL); **no table, column, or migration**. |
| **Container bindings** | `PageBuilderServiceProvider::register()` (LT-8D block) | Binds all four ports; `ReviewApprovalService`/`ReviewWorkspaceInteractor`/`ReviewWorkspaceView` autowire. |
| **Plugin registration** | `plugin.json` `filament.pages` | Registers `LayoutTranslationReviewPage` (deep-link, flag + permission gated). |

Only these files were added/edited. Nothing under `Review/` (runtime), `ReviewUi/` (presentation), the
coordinator, or the diff/planner was touched.

---

## 3. Constraint compliance

| Constraint | Compliance |
|---|---|
| No Workspace Runtime changes | `Review/` untouched; adapters live in the new `ReviewInfrastructure/` namespace and only *implement* the frozen contracts. |
| No Presentation changes | `ReviewUi/`, the Filament page class, and the Blade views are unchanged; only `plugin.json` registration was added. The page's existing `Throwable → fail-soft blocked` path already handles the fail-closed provider. |
| No Coordinator changes | The coordinator is consumed unchanged, via `ReviewApprovalService`. |
| No Diff/Planner changes | `StructuralDiffEngine` and `SynchronizationPlanner` are consumed verbatim; the provider adds no classification. |
| No persistence redesign | The session store is cache-only (ephemeral); the baseline seam adds no storage. No migration, table, or column. |

---

## 4. Verification results

**Standalone harness — `_lt8d_verify.php`: 18 passed, 0 failed.** Covering:

- provider composes LT-5 diff + LT-6 planner into a plan (1 safe, 1 review, 0 conflict; correct item order
  and classification) with **coordinator-verifiable provenance** (stored baseline fingerprint +
  `fingerprint(S)` + `fingerprint(T)`);
- provider **fails closed** on: unavailable baseline, baseline-fingerprint mismatch, and unbound target;
- preview renders S/T content, is fail-soft (null baseline side, unavailable when unbound), and never leaks
  the correspondence key;
- cache session store round-trips a payload-safe snapshot and forgets it (real Illuminate `ArrayStore`);
- static checks: the provider binds all four ports, `plugin.json` registers the page, the page gates on the
  feature flag.

**Real container + feature-flag boot check** (app kernel booted, no DB writes):

```
RESOLVED  ReviewInputProviderInterface   => PlanningReviewInputProvider
RESOLVED  PreviewProjectionInterface     => LayoutPreviewProjection
RESOLVED  ReviewSessionStoreInterface    => CacheReviewSessionStore
RESOLVED  BaselineDocumentSourceInterface=> UnavailableBaselineDocumentSource
RESOLVED  ReviewWorkspaceInteractor      => ReviewWorkspaceInteractor   (full dependency graph)
RESOLVED  ReviewWorkspaceView            => ReviewWorkspaceView
ALL LT-8 BINDINGS RESOLVE
FLAG off => canAccess=false (expected false)
```

The whole LT-8 chain resolves from the live container (the plugin provider is active), and the review page
is inaccessible while the translation flag is off. `php -l` clean across all new PHP.

---

## 5. Browser verification (procedure + current expected states)

The route is `/{admin}/page-builder/translation-review/{ownerType}/{ownerId}/{locale}` (deep-link;
permission `page-builder.edit` + flag `page-builder.translation.enabled`). In this environment a full
click-through cannot be automated; the procedure and the states verifiable now:

1. **Flag off (default):** the page is inaccessible (`canAccess()` false → 403 / hidden). **Verified at
   runtime above.**
2. **Flag on, target not reviewable / baseline document unavailable:** the page loads and renders the
   **fail-soft blocked** state ("Layout translation review is not available for this target") — because the
   baseline-document seam returns null by design this phase. This is the expected steady state until the
   baseline-persistence phase.
3. **Flag on, baseline document available (future phase):** the tree, conflict panel, decision controls,
   and submit populate. A **populated** browser review therefore awaits the deferred baseline-document
   persistence; the flag-gate and fail-soft paths are fully exercisable today.

---

## 6. Regression verdict

The LT-8D wiring is **consistent with the frozen LT-7/LT-8A/LT-8B contracts, the LT-8-PRES presentation
governance, and every constraint of this phase.** It binds the three runtime ports (plus the baseline
seam), registers the entry point, and resolves end-to-end in the live container; the feature flag gates
access; the provider composes the frozen diff/planner with coordinator-consistent provenance and fails
closed; preview is lock-free and fail-soft; and the session store is ephemeral. It introduces **no
Workspace Runtime, Presentation, Coordinator, Diff/Planner, or persistence-redesign change.**

The Review Workspace is **architecturally and infrastructurally complete** for the wired paths. One
explicit, approved follow-up remains before a review can populate in the browser: a persistence phase that
supplies baseline documents through the `BaselineDocumentSourceInterface` seam (no runtime/presentation
change required to adopt it).

**LT-9 (History UX) may be proposed next. Stop after the regression verification.**
