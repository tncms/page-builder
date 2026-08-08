# LT-7 — Architecture Regression Review

**Status: ARCHITECTURE ONLY (LT-7A).** Verifies that the execution architecture
([`LT-7-EXECUTION-ARCHITECTURE.md`](./LT-7-EXECUTION-ARCHITECTURE.md)) preserves every frozen invariant
and contract of LT-0…LT-6, changes no Core, and honours the LT-7A mandatory invariants. This is a
design-level review; it defines the proof obligations that the LT-7 *implementation* phase must satisfy
with tests before it may write code.

---

## 1. Frozen invariant preservation (LT-0-ADR-001)

| Invariant | Statement | How LT-7 preserves it |
|---|---|---|
| INV-LT-01 | Source never mutated during creation or sync. | Source is read once into an immutable snapshot; never locked, never written, never inside the transaction (§4, TG §7.8). |
| INV-LT-02 | Full target document never replaced; only planned, accepted items mutate it. | `T′` working copy **starts as `T`**; only accepted operations touch corresponded nodes; S is never copied over T as a document. Prohibition is a proof obligation (§4 below). |
| INV-LT-03 | Target-owned / target-modified nodes never auto-removed or auto-overwritten. | Applier copies every unnamed node through verbatim; `RemoveNode` refuses subtrees containing target-owned nodes (→ `CorrespondenceFault`); kept items produce no operation. |
| INV-LT-04 | Translated text never auto-overwritten. | `ContentUpdated` only overwrites own-data when the reviewer **explicitly** accepts it; default `KeepTarget` produces no operation. No automation ever overwrites content. |
| INV-LT-05 | Every applied sync reversible via history/baseline; a failed sync leaves no partial layout. | Single atomic transaction (no partial state); history row captures prior baseline fingerprint + applied origins/decisions (reversal data). |
| INV-LT-06 | Locale read-only from Core; no plugin locale/translation-group registry. | LT-7 reads locale/keys only through existing LT-1/LT-2 seams; it creates no registry. |
| INV-LT-07 | Default-locale single-language layouts byte-identical while flag off. | Feature flag gates A1; with the flag off execution never runs; no runtime path changes for single-language sites. |

## 2. Upstream contract-freeze verification

| Layer | Contract | LT-7 relationship | Change? |
|---|---|---|---|
| LT-1 | Descriptor / keys / status / capability resolver (`supportsSync`) | consumes | none |
| LT-2 | Binding model / repository / reader | consumes (reader for target binding) | none |
| LT-3 | Clone service, `TransactionRunnerInterface`, `SourceLayoutDocumentReaderInterface`, failure/transaction governance | reuses runner + source reader; extends failure/transaction discipline | none |
| LT-4 | `BaselineFingerprint`, `LayoutBaseline`, baseline repository, correspondence (`data['_origin']`) | consumes fingerprint + baseline; advances baseline via the repository upsert seam | additive only — baseline `advance()`/upsert on the existing unique key; **no VO/contract break** |
| LT-5 | `StructuralDiffEngine`, `NodeChangeType`, three-way diff (read-only, deterministic) | **does not call at execute time** (policy-free); `NodeChangeType` is read from plan items only | none |
| LT-6 | `SynchronizationPlan` / `…PlanItem` (immutable, payload-safe) | consumes verbatim; never modifies, never re-plans | none |

**Baseline-advance seam note.** If the LT-4 `record()` is insert-only against `UNIQUE(page_translation_
key)`, LT-7 requires an **additive** upsert path (`advance()`), anticipated by D10 ("baseline advanced on
success"). This is additive to the LT-4 repository, breaks no existing method signature, and is the only
new write seam LT-7 introduces on an existing layer.

## 3. Mandatory LT-7A invariant compliance

| Mandatory invariant | Compliance |
|---|---|
| Consumes an immutable `SynchronizationPlan` | The plan is a constructor input, never mutated; execution reads `items()` only. |
| Execution is policy-free | LT-7 derives **no** classification and calls **no** planner/diff at execute time. It lowers already-classified items to operations and reads S only to fetch approved node bytes. |
| Execution never recomputes the diff / reinterprets policy | The consistency guard is a **content-addressed fingerprint equality check** (`PlanProvenance`), not a diff recomputation. Drift → `PlanStale` (caller re-plans); LT-7 never re-decides. |
| Fail-closed | Every gate defaults to *refuse and preserve target* (Failure Model). |
| Target-only | Only `T` is mutated; the applier's working copy starts from `T`. |
| Never mutates Source | INV-LT-01 (row 1). |
| Never mutates Baseline | Baseline is read-guarded; the only write is the **post-success advance** (recording new provenance, preserving prior in history). No mid-flight baseline edit. |
| History append-only | Rows only inserted, one per `Executed`; never updated/deleted; written inside the transaction. |
| Fully transactional | Single atomic unit: `saveDraft(T′)` + baseline advance + history (TG §7.3). |

## 4. Payload-safety proof obligations (implementation phase)

The LT-6 plan's payload-safety must survive the execution boundary. The implementation must prove (with
unique content-token tests, as LT-5/LT-6 did) that **node content never escapes the transaction**:

- The `SynchronizationPlan`, `PlanProvenance`, `SynchronizationAcceptanceSet`,
  `SynchronizationExecutionResult`, `SyncHistoryRecord`, and `SynchronizationExecutionDiagnostics` output
  contain **no** node content — origins, enums, counts, and fingerprints only.
- `SyncOperation.nodePayload` (the only content-bearing field) exists **only** inside Phase B and is
  discarded on commit/rollback. It is never returned, logged, or persisted outside `pb_layouts.draft`.
- The history `applied_items` JSON carries `{origin, source_change, decision}` only — asserted content-
  free with unique tokens embedded in `S`/`T`.

## 5. Full-document-replacement prohibition (D10 / INV-LT-02)

A dedicated regression obligation for the implementation phase:

- A test must construct an `S` and `T` that differ widely, accept a subset of items, and assert `T′`
  retains every target-owned and every kept node **byte-for-byte**, and that `T′ != S`.
- A test must assert there is **no code path** in which `saveDraft` receives `S` (or an `S`-rooted
  document) — the working copy is always `T`-rooted. Full replacement is prohibited and tested.

## 6. Determinism

- Operation lowering and application are deterministic (ordered by origin, matching the sorted plan).
  Identical `(plan, provenance, acceptance, S, T)` → byte-identical `T′`, `result`, and history
  `applied_items`. This mirrors the LT-5/LT-6 determinism guarantee and is a proof obligation.

## 7. Core & runtime isolation

- **No Core file changed**, no new Core port required (LT-0 D11). Dependency direction is Translation →
  Page Builder only; no Page Builder core or CMS Core file is aware of the Execution layer.
- Flag-gated and dormant: with `page-builder.translation.enabled` off, no execution path runs and no
  single-language runtime path changes (INV-LT-07).

## 8. Regression verdict

The LT-7A architecture is **consistent with all frozen contracts and invariants**. It introduces exactly
one additive seam on an existing layer (LT-4 baseline `advance()`/upsert, anticipated by D10) and two new
additive tables (`pb_layout_translation_sync_history`; a baseline advance needs no new table). No Core,
no frozen-contract, and no runtime change. Implementation may proceed against these proof obligations in a
later phase — **not now.** Stop after architecture.
