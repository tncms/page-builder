# LT-10 — Ownership Matrix

> Documentation only. The definitive, no-overlap ownership boundary between Core and the Page Builder Layout
> Translation subsystem. Companion to the
> [Architecture Freeze Certificate](LT-10-ARCHITECTURE-FREEZE-CERTIFICATE.md) and the permanent
> [Plugin/Core Boundary](../../../docs/architecture/PLUGIN_CORE_BOUNDARY.md).

## Principle

**Core is the platform; the subsystem is a consumer.** Every responsibility below has exactly **one** owner.
There is **no overlap** and no shared/ambiguous responsibility.

## Core owns (consumed by the subsystem, never modified or re-implemented)

| Responsibility | Core authority (contract/service) |
|---|---|
| **Locale authority** — the set of locales, default locale, validity | `LanguageConfigurationContract` / `LanguageManager` |
| **Language configuration** | Core localization config |
| **Translation groups** — cross-locale entity grouping identity | `LocalizedEntityInterface`, `TranslatablePluginContent::translationGroup()` |
| **Localized entities** — the canonical content entities and their per-locale rows | Core content models (e.g. `ContentTranslation`) |
| **Translation lifecycle** — entity-level create/translate/publish of localized entities | Core Translation Platform (`ContentManager` + drivers) |

The subsystem reads these through Core public contracts only. It never writes Core-owned state, never
decorates a Core service, and never re-creates a Core capability locally.

## Page Builder owns (this subsystem, in full)

| Responsibility | Subsystem authority |
|---|---|
| **Layout clone** — deriving a target-locale layout document from a source | `LayoutTranslationCloneServiceInterface` + Cloning services |
| **Correspondence** — stable cross-locale node identity (`data['_origin']`) | Correspondence / Identity value objects |
| **Baseline** — content-addressed record of what a target was translated from | `LayoutBaselineRepositoryInterface`, `BaselineFingerprint` |
| **Diff** — three-way structural comparison | `StructuralDiffEngine` + Diff value objects |
| **Synchronization planning** — reviewable, fail-closed classification of changes | `SynchronizationPlanner`, `SynchronizationPlan` |
| **Execution** — atomic apply of accepted changes (single authority) | `SynchronizationCoordinatorInterface` |
| **Review** — human, per-node decision workspace | Review workspace contracts + `ReviewApprovalService` |
| **Timeline** — append-only history read model, replay, explainability | Timeline contracts + read model |
| **Recovery** — orchestration above the timeline that delegates to the coordinator | Recovery contracts (`ADR-LT-9D`) |

All of the above are Page-Builder-namespaced (`Plugins\PageBuilder\LayoutTranslation\…`) and Page-Builder-owned.

## The boundary line

```
Core Translation Authority
   • locale authority
   • language configuration
   • translation groups
   • localized entities
   • translation lifecycle
        │  (public contracts; read-only consumption)
        ▼
Page Builder Layout Translation
   • layout clone · correspondence · baseline · diff
   • planning · execution · review · timeline · recovery
```

## Explicitly NOT owned by the subsystem

- Entity-field (scalar) translation — **Core**'s Translation Platform.
- Any cross-plugin translation orchestration — does not exist; would require an extraction ADR.
- Locale resolution, fallback chains, or active-locale selection — **Core**.

## No-overlap assertion

Every row above has a single owner. Where the two domains meet (a translated layout belongs to a localized
entity), the subsystem **reads** the Core-owned owner identity (via a Core model/contract) and **writes only**
its own `pb_layout_translation_*` state. No responsibility is shared, duplicated, or negotiated at runtime.
