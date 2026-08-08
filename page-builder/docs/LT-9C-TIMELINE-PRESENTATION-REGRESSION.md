# LT-9C — Timeline Presentation & Infrastructure Wiring: Deliverable

**Status: INFRASTRUCTURE + PRESENTATION IMPLEMENTED (LT-9C). Read-only. No Timeline Runtime / Replay /
Snapshot / History redesign, no Coordinator / Planner / Diff / Core / Execution change.** This is the LT-9C
deliverable: architecture audit, infrastructure adapter tree, application service tree, Filament page tree,
authorization matrix, verification results, and regression verification.

Verified by a standalone harness ([`_lt9c_verify.php`](./_lt9c_verify.php), **27/27**) plus a real
app-boot container + authorization check (all bindings resolve; the Eloquent reader is fail-soft; the flag
gate holds).

---

## 1. Architecture audit

| Concern | Finding |
|---|---|
| **Runtime untouched** | The frozen LT-9B runtime subtree is byte-unchanged; its own harness still passes **24/24**. |
| **Presentation → Runtime only** | The Filament page constructs no runtime, queries no history model, and runs no replay/diff/planner logic — it obtains every timeline, list, replay report, and explanation through the application/query services (INV-LT-9C-01). |
| **Adapters translate persistence only** | The Eloquent reader maps rows → immutable DTOs; the snapshot adapter delegates to a backend. Neither contains a domain engine (INV-LT-9C-02). |
| **Replay is observational** | The UI *requests* replay; only `ReplayRuntime` reconstructs. The page holds no diff/applier/coordinator; a static scan confirms it (INV-LT-9C-03). |
| **No raw snapshot content in the UI** | Documents cross only into the runtime (replay/explain), whose reports carry no content. Blades render references + reports + counts; a content-token/`sections` scan finds none (INV-LT-9C-04). |
| **Read-only** | No service or page exposes a write/mutate/execute/delete method (reflection + static scan). No undo/rollback/re-execution exists. |
| **Fail-soft** | The snapshot backend defaults to `Null` (LT-8.5 not yet implemented) ⇒ replay `Unavailable`, explainability `MetadataOnly`; an unmigrated history table ⇒ an empty timeline. No crash. |
| **Container** | All seven LT-9C bindings resolve in the live app; the Eloquent reader implements the frozen port; the flag gate returns false when the feature is off. |

---

## 2. Infrastructure adapter tree

New subtree `src/LayoutTranslation/TimelineInfrastructure/` (read-only; implements the frozen LT-9B ports):

```
TimelineInfrastructure/
  EloquentTimelineHistoryReader          implements SynchronizationHistoryReaderInterface
                                         (pb_layout_translation_sync_history → TimelineHistoryRecord[], fail-soft)
  TimelineSnapshotBackendInterface       the seam to a snapshot store
  NullTimelineSnapshotBackend            fail-closed default (LT-8.5 not yet implemented)
  SnapshotTimelineDocumentResolverAdapter implements TimelineSnapshotSourceInterface (delegates to backend)
```

## 3. Application service tree

```
TimelineInfrastructure/
  TimelineApplicationService   read facade: timeline() · replay() · explain() · statistics()
                               (constructs the timeline via the runtime builder; delegates replay/explain)
  TimelineQueryService         read list: list()/projection() — DOMAIN filtering via runtime projections;
                               sorting/pagination are view mechanics only
  TimelineListResult           immutable, paginated view model (references + counts; never content)
```

Both services are read-only and delegate all domain behavior to the frozen runtime.

## 4. Filament page tree

```
Filament/Pages/
  LayoutTranslationTimelinePage   deep-link /page-builder/translation-timeline/{ownerType}/{ownerId}/{locale}
                                  ephemeral view state only; delegates to the services
resources/views/filament/pages/
  layout-translation-timeline.blade.php       shell
  translation-timeline/_statistics.blade.php  statistics + chain state
  translation-timeline/_list.blade.php        list: filter · gaps-only · sort · paginate
  translation-timeline/_detail.blade.php      metadata + snapshot REFERENCES + chain info
  translation-timeline/_replay.blade.php      request replay → Reproduced/Diverged/Unavailable + difference
  translation-timeline/_explainability.blade.php  request explanation → Full/MetadataOnly node table
```

Registered in `plugin.json` (`filament.pages`); flag- and permission-gated; fail-soft blocked when
unavailable.

## 5. Authorization matrix

| Capability | Permission | Enforced at |
|---|---|---|
| Open the timeline (route + view) | `timeline.view` | `canAccess()` (+ feature flag) and `mount()` (`abort_unless`) |
| Request a replay | `timeline.replay` | `requestReplay()` (`abort_unless`) + replay panel guard |
| Request an explanation | `timeline.explain` | `requestExplain()` (`abort_unless`) + explain panel guard |
| Undo / rollback / re-execute / edit / delete | — | **not implemented; the timeline is read-only** |

Replay permission is **separate from execution permission** (`page-builder.edit`): being able to *observe* a
replay never grants the ability to *execute* a sync. All three permissions are registered in the permission
manager.

## 6. Verification results

**Standalone harness — `_lt9c_verify.php`: 27 passed, 0 failed.** Covering:

- infrastructure conformance + snapshot adapter fail-soft/delegation;
- application service delegates timeline/statistics to the runtime;
- replay observational (`Unavailable` without snapshots, `Reproduced` with, missing entry → `Unavailable`);
- explainability degrades (`MetadataOnly`) / re-derives (`Full`);
- query service: actor filter + gaps-only via runtime projections; sort/pagination as view mechanics; no
  snapshot content in the list;
- services expose no write method;
- static boundary: adapters add no domain behavior; the page constructs no runtime, queries no history
  model, exposes no write/replay-logic, obtains data only via the services, and renders no raw snapshot
  content;
- authorization: view/replay/explain gating, permission registration, page registration.

**Real app-boot container + authorization check:**

```
RESOLVED  SynchronizationHistoryReaderInterface => EloquentTimelineHistoryReader
RESOLVED  TimelineSnapshotSourceInterface       => SnapshotTimelineDocumentResolverAdapter
RESOLVED  TimelineBuilder / ReplayRuntime / ExplainabilityRuntime
RESOLVED  TimelineApplicationService / TimelineQueryService
reader implements port: true
timeline builds (fail-soft) entries=0
FLAG off => canAccess=false (expected false)
ALL LT-9C BINDINGS RESOLVE
```

`php -l` clean across all new PHP; `plugin.json` valid; the LT-9B runtime harness still **24/24**.

## 7. Ratified invariants (LT-9C lock)

| # | Invariant | Enforced by |
|---|---|---|
| **INV-LT-9C-01** | **Presentation never bypasses the Timeline Runtime** — every view is constructed exclusively through the runtime (via the services). | Page constructs no runtime and queries no history model (static scan); data comes only from the services. |
| **INV-LT-9C-02** | **Infrastructure adapters translate persistence only** — they introduce no domain behavior. | Reader maps rows→DTOs; adapter delegates to a backend; neither imports a domain engine (static scan). |
| **INV-LT-9C-03** | **Replay presentation is observational** — the UI may request replay; only `ReplayRuntime` reconstructs; the UI performs no replay logic. | Page holds no diff/applier/coordinator; `requestReplay` is a single delegation (static scan + reflection). |
| **INV-LT-9C-04** | **Timeline UI never exposes raw snapshot content** — presentation uses reports, references, and reconstructed outputs only. | Documents reach only the runtime; blades render references/reports/counts; content scan finds none. |

These compose with, and never weaken, INV-LT-9B-RT-01…03, INV-LT-9-TL-01…06, and every earlier frozen
invariant.

## Regression verification

The LT-9C wiring is **consistent with the frozen LT-9A/LT-9B contracts and every earlier invariant.** It
adds read-only infrastructure adapters, read-only application services, and a read-only Filament
presentation — all consuming the frozen Timeline Runtime as the single source of domain behavior. It
implements **no** undo/rollback/re-execution/history-edit/snapshot-edit/baseline-edit/delete, and changes
**no** runtime/coordinator/planner/diff/history/snapshot/Core/execution code. The snapshot backend is
fail-closed pending the LT-8.5 store; adopting a real backend later is a one-line binding change with no
presentation or runtime change.

**Stop after LT-9C verification. LT-9D is not started.**
