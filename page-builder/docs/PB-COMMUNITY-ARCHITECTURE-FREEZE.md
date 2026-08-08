# PB-COMMUNITY-ARCHITECTURE-FREEZE — Page Builder Community Foundation

**Status:** Accepted — Community Foundation Frozen (automated + build + JS gates GREEN; live EN/VI operator acceptance recorded; runtime + foundation freeze commits landed at PB-CE-FREEZE-2R)
**Date:** 2026-08-06
**Phase:** PB-CE-FREEZE-1
**Scope:** Community Edition Page Builder foundation only. No Cloud / Collaboration / Revisions / AI / Autosave feature work.

---

## 1. Freeze status

The five foundation ADRs are complete and their automated gates pass. Both prior
human/host gates are now satisfied: (a) live EN/VI browser acceptance was recorded by the
operator (VI publish targets the VI owner; VI frontend renders VI; EN unchanged), and
(b) the authorized freeze commits landed at PB-CE-FREEZE-2R (runtime closure + this
foundation commit). Status: **Accepted — Community Foundation Frozen.**

## 2. Included ADRs

| ADR | File | Status | Automated gate |
|---|---|---|---|
| Effective Layout Owner Resolution | `ADR-PB-EFFECTIVE-LAYOUT-OWNER-RESOLUTION.md` | Accepted | Owner + OwnerGuard suites |
| Data Architecture | `ADR-PB-DATA-ARCHITECTURE.md` | Accepted | DataArchitectureGuard |
| Runtime & Rendering | `ADR-PB-RUNTIME-RENDERING-ARCHITECTURE.md` | Accepted | RuntimeArchitectureGuard |
| Component Domain | `ADR-PB-COMPONENT-DOMAIN-ARCHITECTURE.md` | Accepted | ComponentArchitectureGuard |
| Behavior & State | `ADR-PB-BEHAVIOR-STATE-ARCHITECTURE.md` | Accepted | BehaviorStateGuard + Vitest |

Supporting frozen ADRs: `ADR-PB-RENDER-SOURCE-001` (Accepted), `ADR-PB-TRANSLATABLE-FIELD-REPRESENTATION` (Accepted).

## 3. Frozen authorities

```text
Owner     → EffectiveEditorOwnerResolver (resolve fail-closed / resolveForReadOrCanonical fail-soft)
Layout    → LayoutStore (sole writer of draft/published/editor_mode)
Payload   → LayoutNormalizer (single render normalizer) + currentPayload (client)
State     → Pinia editor store (dirty/saving/publishing/publicationState) + derived server truth
Runtime   → LayoutRenderer → ComponentTree → ComponentFactory → ComponentRuntime
Component → ComponentDefinition + Section/BlockRegistry
Render    → resolveThemeOrPlugin → View::make (+ RuntimeHookManager, single hook boundary)
Publish   → LayoutStore::publish (whole-draft atomic copy)
Locale    → Localize + RenderLocale (ADR-TRANSLATABLE); RenderSource → RenderSourceWriter
```

## 4. Runtime pipeline

`Effective owner → exact pb_layouts row → payload authority → LayoutNormalizer → ComponentTree/ComponentFactory → ComponentRuntime (lifecycle + hooks + view + Localize + markers?) → HTML → scoped asset middleware`. One convergence point: `LayoutRenderer`. Deterministic, read-only, zero DB writes, uncached by design (ADR-RUNTIME).

## 5. Component model

One contract (`ComponentDefinition`), one base (`AbstractComponentDefinition`), one registry pair (`Section/BlockRegistry` over `AbstractRegistry`), one lifecycle (`AbstractComponentInstance` via `ComponentFactory`), one capability model (`supports`), one extension seam. 9 sections + 10 blocks (ADR-COMPONENT).

## 6. Behavior/state model

State machine `Booting → Ready → Dirty → Saving → Saved/Unpublished → Publishing → Published` with `SaveFailed/PublishFailed/PreviewFailed`. Publish serializes a successful save; status truth is server-derived; all UI/runtime state transient; autosave absent; undo/redo client-only (ADR-BEHAVIOR).

## 7. Mutation contracts

All persisted writes route through `LayoutStore`; mutations fail closed (owner/locale); publish is a whole-draft atomic copy; Render Source writes fail closed; translation provisioning writes the target **draft** only. No per-element publish.

## 8. Public / editor boundaries

Public frontend reads `published_payload` only, has no editor markers, loads no editor bundles, writes no DB. Preview/editor read draft/posted payload and never mutate persistent state. Markers are editor-mode presentation only.

## 9. Community guarantees

Single-user editor session · draft/save/publish integrity · EN/VI isolation · whole-layout publish · owner-scoped Render Source · deterministic preview/frontend render · component-registry extensibility · stable command/history model · plugin-owned architecture · no hidden Cloud dependency.

## 10. Extension seams

Component registry (`registerSection/registerBlock` + views) · runtime resolve steps (responsive/tokens/dynamic/conditions) · theme view overrides · runtime hooks (4 points) · client extension API (`ExtensionApiFreeze`). Future features extend these; they must not fork the pipeline.

## 11. Deferred Cloud capabilities (`FUTURE_EXTENSION`)

Advanced autosave scheduling · Realtime Collaboration · Cloud Data · Revision cloud sync · Branch/Merge · Team Workspace · remote data providers · AI command service. **Community runtime depends on none of these.**

## 12. Forbidden architectural changes

Second owner resolver / normalizer / runtime factory / render pipeline / translation representation / Render Source authority; frontend reading draft; preview reading stored published; persisting UI or publication state; hidden render cache; per-element publish; client-authoritative HTML/publish-state; Core acquiring PB knowledge.

## 13. Review process for future ADR changes

A change to any frozen authority requires a new ADR that supersedes the affected one, re-runs the full guard matrix, and preserves Community contracts (CE-FREEZE-09). Guards may not be weakened to pass.

## 14. Guard suites (permanent, tracked)

| Suite | Tests |
|---|---|
| PageBuilderEffectiveOwnerResolutionTest | 12 |
| PageBuilderOwnerResolutionArchitectureGuardTest | 7 |
| PageBuilderDataArchitectureGuardTest | 7 |
| PageBuilderRuntimeArchitectureGuardTest | 16 |
| PageBuilderComponentArchitectureGuardTest | 4 (640 assertions) |
| PageBuilderBehaviorStateGuardTest | 5 |
| Client Vitest (publicationState/publishAtomicity/StatusBar/localeSwitch/ExtensionApiFreeze + 169 more) | 174 files / 1456 |

## 15. Live acceptance

**Not performed** — no booted admin in the CLI harness. Steps documented in the ADRs' acceptance checklists (open EN/VI, edit, save, preview, publish once, verify draft==published for the active locale + other locale unchanged + public frontend + marker/asset isolation + reload hydration + undo/redo + controlled save/publish failure recovery). Verdict A is forbidden until this passes.

## 16. Repository / source-control status

Index empty at freeze. This phase's artifacts (5 ADRs across the series + this freeze doc + 6 guard test files) are untracked-but-visible in tracked paths (`plugins/page-builder/docs/**`, `tests/Feature/PageBuilder/**`) — **no `git add -f` needed**. Generated assets `/public/vendor/page-builder` are ignored + reproducible via `npm run build`. Temporary `docs/_lt*_verify.php` harnesses are retained-documented (linked by LT docs), not promoted. The single freeze commit is pending explicit authorization.

## 17. Known non-blocking debt

| ID | Item | Classification | Note |
|---|---|---|---|
| D1 | Uncommitted foundation ADRs + guards (this series) | DEFERRED_COMMUNITY | awaiting one authorized freeze commit |
| D2 | Live EN/VI browser acceptance | DEFERRED_COMMUNITY | needs booted admin; blocks Verdict A only |
| D3 | Multiple `*Normalizer` classes | FALSE_POSITIVE | DesignCapture (reuses SCHEMA) + DynamicStyle (CSS) are separate domains, not render normalizers |
| D4 | `docs/_lt*_verify.php` harnesses | RETAIN_DOCUMENTED | authoritative LT docs link them |
| D5 | Pre-existing PB-UI-1/2V dirt in working tree | DEFERRED_COMMUNITY | not part of this freeze; untouched |
| D6 | Autosave / Revisions / Collaboration | CLOUD_ONLY | documented future extensions |

No `FIX_NOW` debt remains open; no `BLOCKER`.

## 18. Final checklist

- [x] Five foundation ADRs complete.
- [x] All debt classified; no FIX_NOW open; no blocker.
- [x] Owner parity proven (Stage 3 re-cert).
- [x] Data authority proven (Stage 4 re-cert).
- [x] Runtime pipeline proven (Stage 5 re-cert).
- [x] Component model proven.
- [x] Behavior state machine proven; async races guarded.
- [x] Community boundary documented; no Cloud dependency.
- [x] All permanent tests tracked; generated-asset policy explicit.
- [x] Full PHPUnit 595/595 · Vitest 174/1456 · builds 4/4 · lint clean · plugin.json valid.
- [x] No Core / schema / migration change.
- [ ] Live EN/VI workflow passed.
- [ ] Freeze commit authorized + applied.

Flip to **Accepted — Community Foundation Frozen** once the final two boxes are checked.
