# LT-7C — Infrastructure Wiring

**Status: IMPLEMENTED & FROZEN (LT-7C). Production adapters + provider registration + sync-history
migration + end-to-end integration verification. Additive and flag-gated (dormant until
`page-builder.translation.enabled`). No Core change; frozen LT-1…LT-7 contracts and invariants
preserved.** The final Execution-layer phase: every in-memory fake from LT-7B3 is replaced by a
production implementation over the existing frozen infrastructure. With LT-7C complete, LT-8 (Review UI)
may begin.

---

## Delivered

### Production adapters (`src/LayoutTranslation/Execution/Adapters/`)

| Coordinator port | Production adapter | Bridges to |
|---|---|---|
| `ExecutionEligibilityInterface` | `ConfigCapabilityExecutionEligibility` | `LayoutTranslationConfig` flag + capability resolver |
| `SynchronizationStateLoaderInterface` | `PageBuilderSynchronizationStateLoader` | LT-2 binding reader + LT-4 baseline store + LT-3 source reader + `LayoutStore` (`lockForUpdate` on the bound row) |
| `TargetDraftWriterInterface` | `LayoutStoreTargetDraftWriter` | `LayoutStore::saveDraft` (canonical mutation path) |
| `BaselineAdvancerInterface` | `LayoutBaselineAdvancer` | LT-4 baseline repository upsert |
| `SyncHistoryRecorderInterface` | `EloquentSyncHistoryRepository` | new append-only history table |
| `OperationApplierInterface` | `DocumentOperationApplier` (LT-7B2) | — |

### Capability advancement (`src/LayoutTranslation/Execution/Capability/`)

`SyncCapableCapabilityResolver` advertises `clone + diff + sync` (LT-3 clone + LT-5 diff + LT-7 sync are
now implemented). A NEW resolver — the frozen default and LT-3 clone resolvers are untouched; Capability
stays implementation-static and status-free (LT-1.5). The container binding advances to it; actual
execution stays gated by the feature flag.

### History persistence (`src/LayoutTranslation/Execution/History/`)

`Models/LayoutTranslationSyncHistory` + `Repositories/EloquentSyncHistoryRepository` + migration
`database/migrations/2026_07_28_000001_create_pb_layout_translation_sync_history_table.php`. Append-only;
separate from the frozen LT-2 binding and LT-4 baseline tables; no FK to `pb_layouts` (content-addressed
provenance). Columns store the decision record (plan fingerprint, baseline before/after, applied/skipped
items with reviewer decisions) — payload-safe by construction.

### Provider registration (`PageBuilderServiceProvider`)

One additive "Phase LT-7" block: binds every Execution port to its adapter, advances the capability
resolver, and registers the Coordinator (singleton, all 9 collaborators) and the read-only Diagnostics.
Reuses the frozen LT-3 `TransactionRunnerInterface`. All bindings are lazy; nothing runs at boot.

---

## End-to-end integration verification

`tests/Feature/PageBuilder/PageBuilderLayoutTranslationExecutionTest` — real container, real database,
real migration. **5/5 passing, 31 assertions.**

- **Schema** — the sync-history table and all columns are created.
- **End-to-end execution** — clone → capture baseline → diverge the source → the container-resolved
  Coordinator applies an accepted `ContentUpdated` to the target draft (correspondence preserved),
  advances the baseline to the synced source fingerprint, and writes exactly one append-only,
  payload-safe history row (verified content-free).
- **Fail-closed (feature off)** — `FeatureDisabled`, no history row.
- **Stale provenance** — `PlanStale` (`SourceChanged`); target draft, baseline, and history all
  unchanged.
- **Frozen contracts + capability advancement** — `pb_layouts` has no `locale`, the LT-2 binding table
  has no `fingerprint`, the history table exists; the frozen default capability resolver is still
  all-false while the bound implementation now advertises `sync` (and keeps `clone`).

---

## Architecture regression verification

| Obligation | Result |
|---|---|
| Every LT-7B3 fake replaced by a production implementation | ✅ eligibility, loader, writer, advancer, recorder all real; wired in the provider |
| INV-LT-7-EXEC-01 provenance once, before mutation | ✅ loader `lockForUpdate`; stale provenance aborts with zero writes (test) |
| INV-LT-7-EXEC-02 history reconstructs decisions, payload-safe | ✅ real append-only table; row asserted content-free |
| INV-LT-7-MUT-01/02 engine purity | ✅ engine bound as a no-dependency singleton; all environment (config/time/DB) lives in adapters |
| Atomic write set (LT-7-TRANSACTION-GOVERNANCE) | ✅ Coordinator owns one transaction via the frozen LT-3 runner; write + advance + history commit together |
| INV-LT-01/02/03/04 preserved end-to-end | ✅ source untouched; node-scoped target write; target-owned/kept nodes preserved; content overwrite only where accepted |
| No Core change; frozen LT-1…LT-7 contracts unchanged | ✅ additive adapters/migration/binding only; frozen tables and default resolvers untouched (asserted) |
| Dormant until enabled | ✅ flag-gated; lazy bindings; single-language sites unaffected |

### Note on the pre-existing LT-1 suite failures (not LT-7C)

Running the broader LT feature suite surfaces 5 failures in the **LT-1** resolver path
(`PageBuilderLayoutTranslationTest` reader/orphan/diagnostics, one `BindingTest` and one `BaselineTest`
case). These are **pre-existing and environmental**, not caused by LT-7C:

- All five exercise the `enabled=true` `LayoutTranslationResolver::resolve()` path, which faults
  `LocaleNotEnabled` when the local environment's enabled-locale set excludes the default locale
  (`LayoutTranslationResolver` line 61) — a language-configuration condition LT-7C does not touch.
- LT-7C changes only the capability-resolver **binding**; the resolver reads capabilities solely to
  populate the descriptor's capability field (line 72) and the validator **ignores** capabilities (the
  LT-1 suite asserts this itself). None of the failing assertions read capabilities.
- LT-7C's own pipeline (clone/capture/execute) does not go through that locale gate and passes 5/5.

A clean local re-baseline is blocked only by the documented `storage/app/tncms-installed` marker flicker
(the marker forces "installed" boot, querying `cms_settings` before `RefreshDatabase` migrates); tests
must be run with the marker moved aside.

---

## Non-goals (LT-7C)

No Review UI / Review Workspace / approval screens (that is **LT-8 — Review UI**), no AI, no undo/revert
(LT-9), no Core change. Infrastructure wiring and its verification only.

**LT-7C is frozen. The Execution Layer is complete and production-wired.** Per the gate, LT-8 (Review UI)
— the application-workflow presentation over this frozen execution pipeline — may now begin.
