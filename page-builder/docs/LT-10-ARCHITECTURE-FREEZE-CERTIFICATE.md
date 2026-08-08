# LT-10 — Architecture Freeze Certificate

> **Official freeze certificate for Page Builder Layout Translation.** Documentation only. This certificate
> records the permanently frozen state of the subsystem as of phase LT-10. Any change to what is certified
> here requires a new ADR ([extension policy](#12-extension-policy)). Companion documents:
> [Compatibility Policy](LT-10-COMPATIBILITY-POLICY.md) · [Maintenance Policy](LT-10-MAINTENANCE-POLICY.md) ·
> [Ownership Matrix](LT-10-OWNERSHIP-MATRIX.md) · [Extension Guide](LT-10-EXTENSION-GUIDE.md) ·
> [Plugin/Core Boundary](../../../docs/architecture/PLUGIN_CORE_BOUNDARY.md).

**Certified name:** Page Builder Layout Translation
**Status:** RELEASED ARCHITECTURE — maintenance mode (see [Maintenance Transition](#13-maintenance-transition))
**Phases certified:** LT-0 → LT-9D (implementation) + LT-9X / LT-9X.1 (boundary & scope correction)
**Freeze date:** 2026-08-01

---

## 1. Implementation scope

A Page Builder plugin subsystem that synchronizes a **structured layout document** across locales through a
disciplined, non-destructive pipeline:

```
clone → baseline → diff → plan → review → execute → snapshot → timeline → recovery
```

All implementation lives under `plugins/page-builder/src/LayoutTranslation/` (namespace
`Plugins\PageBuilder\LayoutTranslation\…`). It is dormant unless `page-builder.translation.enabled` (env
`PAGE_BUILDER_TRANSLATION`) is true; default is **false**.

## 2. Architectural owner

**The Page Builder plugin** owns and maintains this subsystem in full. It is the **reference implementation**
of the layout-document synchronization discipline and the **only** integrator. There is no second production
consumer and no plugin-neutral contract surface.

## 3. Plugin ownership (what Page Builder owns)

Layout clone · correspondence · baseline · structural diff · synchronization planning · execution
(coordinator) · review workspace · timeline · recovery. See [Ownership Matrix](LT-10-OWNERSHIP-MATRIX.md).

## 4. Core ownership (what Page Builder consumes, never owns)

Locale authority · language configuration · translation groups · localized entities · translation lifecycle.
Consumed only through Core public contracts (`LanguageConfigurationContract`, `LocalizedEntityInterface`,
`TranslatablePluginContent`). See [Ownership Matrix](LT-10-OWNERSHIP-MATRIX.md).

## 5. Dependency direction (certified)

```
Core (locale authority · Core Translation Platform)
  ▲  consumed, never modified
Page Builder plugin (owns the layout document)
  ▲  internal seams
Page Builder Layout Translation (this subsystem)
```

Dependencies point **downward only**. Nothing in Core or the Page Builder plugin core depends upward on this
subsystem. This is a **single-plugin stack**, not a `Core → shared platform → plugins` pyramid.

## 6. Supported capabilities

- Non-destructive cross-locale synchronization of a **structured layout document**.
- Content-addressed baseline provenance; three-way structural diff; reviewable, fail-closed plan.
- Human review workspace with per-node decisions; atomic, single-authority execution.
- Append-only history; content-addressed snapshots; navigable timeline; read-only replay and explainability.
- Recovery orchestration that **delegates** to the frozen execution coordinator (never edits history).
- Feature-flag dormancy: with the flag off, no LT path runs and Page Builder behaves exactly as before.

## 7. Unsupported capabilities (explicitly out of scope)

- Any **non-Page-Builder** integrator (ecommerce, knowledge library, etc.) — hypothetical only, requires an
  extraction ADR (see §12 and the Plugin/Core Boundary).
- Field-level / scalar entity translation — that is the **Core** Translation Platform's domain.
- Generalization to arbitrary (non-layout) content shapes — reserved direction, ADR-gated.
- Any shared "TN CMS Translation Platform," shared runtime, generic engine, or plugin SDK — none exists.
- Auto-merge / auto-accept of non-safe changes; direct target/baseline/history/snapshot writes by consumers.

## 8. Frozen public contracts (28 interfaces — all FROZEN)

All interfaces below live in `LayoutTranslation\…\Contracts` and are **FROZEN** (stable signatures; change
only by ADR). Concrete implementations are **INTERNAL** and DI-replaceable behind these ports.

| Group | Frozen interfaces |
|---|---|
| **Identity / capability / state** | `LayoutOwnerLocatorInterface`, `LayoutTranslationCapabilityResolverInterface`, `LayoutTranslationStateReaderInterface`, `SynchronizationStateLoaderInterface`, `LayoutDocumentLocatorInterface` |
| **Cloning** | `LayoutTranslationCloneServiceInterface`, `SourceLayoutDocumentReaderInterface`, `TargetOwnerResolverInterface`, `TransactionRunnerInterface` |
| **Baseline** | `LayoutBaselineRepositoryInterface`, `BaselineAdvancerInterface`, `BaselineDocumentSourceInterface` |
| **Persistence** | `LayoutTranslationBindingRepositoryInterface`, `LayoutTranslationBindingReaderInterface` |
| **Execution** | `SynchronizationCoordinatorInterface` (sole execution authority), `ExecutionEligibilityInterface`, `OperationApplierInterface`, `TargetLayoutWriterInterface`, `TargetDraftWriterInterface`, `SyncHistoryRecorderInterface` |
| **Review** | `PreviewProjectionInterface`, `ReviewInputProviderInterface`, `ReviewSessionStoreInterface` |
| **Timeline** | `SynchronizationHistoryReaderInterface`, `TimelineSnapshotSourceInterface`, `TimelineSnapshotBackendInterface` |
| **Recovery** | `RecoveryCoordinatorGatewayInterface`, `RecoverySourceGatewayInterface` |

**Immutable value objects / enums:** 146 `final` value classes and 28 enums across the subsystem are
**FROZEN as immutable** (construction is INTERNAL; consumers receive them, never mutate them). Reference
adapters (`PageBuilderSourceLayoutDocumentReader`, `LayoutStoreTargetDraftWriter`, `PageBuilderTargetLayoutWriter`,
`PageBuilderSynchronizationStateLoader`, `LayoutBaselineAdvancer`, `ConfigCapabilityExecutionEligibility`,
`IlluminateTransactionRunner`) are the **INTERNAL** default implementations of the frozen seams.

## 9. Frozen invariants (complete index — 45 invariants, 0 contradictions/duplicates/orphans)

| Family | IDs | Count | Source phase |
|---|---|---|---|
| Foundational | INV-LT-01 … INV-LT-07 | 7 | LT-1 (Architecture Lock) |
| Execution | INV-LT-7-EXEC-01 … -02 | 2 | LT-7 |
| Presentation | INV-LT-8-PRES-01 … -02 | 2 | LT-8 |
| Baseline snapshot | INV-LT-8.5-01 … -08 | 8 | LT-8.5 |
| Review workspace | INV-LT-8A-01 … -08 | 8 | LT-8A |
| Timeline | INV-LT-9-TL-01 … -06 | 6 | LT-9A |
| Timeline runtime | INV-LT-9B-RT-01 … -03 | 3 | LT-9B |
| Timeline presentation/wiring | INV-LT-9C-01 … -04 | 4 | LT-9C |
| Recovery | INV-LT-9D-01 … -05 | 5 | LT-9D |

**Invariant audit:** every ID resolves to exactly one definition in its source phase document; the families
are disjoint by prefix; no invariant contradicts another (execution fail-closed, consumption fail-soft, and
append-only history are mutually consistent); no obsolete or orphan invariant found.

## 10. Approved ADR list

| ADR | Subject | Status |
|---|---|---|
| **LT-0-ADR-001** | Layout Translation & Structural Synchronization | Frozen |
| **LT-1-ADR-002** | LT-1 Architecture Lock (foundational invariants) | Frozen |
| **ADR-LT-9D** | Synchronization Recovery Workflow | Frozen |
| Phase freeze/governance records | LT-3 / LT-7 / LT-8.5 / LT-8A Final Governance; LT-9D Recovery Freeze | Frozen |
| Boundary governance | `PLUGIN_CORE_BOUNDARY.md`, `00-SCOPE-CORRECTION.md` (LT-9X.1) | Frozen |

**ADR audit:** no orphan ADR (every ADR maps to shipped, still-present architecture); no ADR contradicts a
frozen invariant; the boundary ADRs supersede all prior "shared platform" wording.

## 11. Architecture verification

| Check | Result |
|---|---|
| No Core dependency inversion | ✓ Core has 0 imports of the subsystem |
| No plugin → Core mutation | ✓ LT consumes Core contracts read-only; writes only `pb_*` tables |
| No runtime overclaim | ✓ flag-gated, dormant by default, not in any render/route path |
| No documentation overclaim | ✓ corrected in LT-9X.1; scan clean |
| No platform overclaim | ✓ certified as a Page Builder subsystem / reference implementation |
| No hidden shared-engine claim | ✓ no shared package, no plugin-neutral contract surface |
| No unsupported integration claim | ✓ cross-plugin examples marked hypothetical/not-supported |

## 12. Extension policy

The certified surface changes **only** through a new ADR. See [Extension Guide](LT-10-EXTENSION-GUIDE.md)
for how each future need is classified (maintenance · additive capability · major redesign · Core evolution ·
plugin extraction · shared-platform proposal) and its required approval path. Promotion to a shared platform
requires the criteria in [Plugin/Core Boundary](../../../docs/architecture/PLUGIN_CORE_BOUNDARY.md) Rules 6–7.

## 13. Maintenance transition

Status is officially changed from **ACTIVE ROADMAP** to **RELEASED ARCHITECTURE (maintenance only)**. Future
work is numbered **LT-M1, LT-M2, LT-M3, …** (not LT-11), scoped by the [Maintenance Policy](LT-10-MAINTENANCE-POLICY.md).

---

**Certified:** Page Builder Layout Translation is architecturally complete and frozen.
