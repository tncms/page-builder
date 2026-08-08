# ADR-PB-RUNTIME-RENDERING-ARCHITECTURE — One Render Pipeline

**Status:** Accepted (architecture lock; automated gates GREEN + live EN/VI operator acceptance recorded; frozen at PB-CE-FREEZE-2R)
**Date:** 2026-08-06
**Phase:** PB-ADR-RUNTIME-1 (runtime/rendering audit + guard lock)
**Depends on (frozen):** ADR-PB-RENDER-SOURCE-001, ADR-PB-TRANSLATABLE-FIELD-REPRESENTATION, ADR-PB-EFFECTIVE-LAYOUT-OWNER-RESOLUTION, ADR-PB-DATA-ARCHITECTURE.
**Supersedes / amends:** nothing. Consolidates the render subsystem into one authority map.

---

## 1. Status

Accepted. Every automated gate passes (`PageBuilderRuntimeArchitectureGuardTest` 16/16 +
full PB suite) and the live EN/VI render acceptance (Work R) was recorded by the operator.
Frozen at PB-CE-FREEZE-2R.

## 2. Context

Owner resolution (ADR-OWNER), data ownership (ADR-DATA) and Render Source are locked. The
last foundation layer before PB-UI-2 / feature work is *how a payload becomes HTML*. This
ADR proves and freezes one render pipeline, one normalizer, one runtime factory, one view
resolver, one localization boundary, one hook boundary, and one asset authority per scope.

## 3. Motivation / incidents

Every render defect in this project's history came from an implicit or duplicated render
authority (ambient-locale value rendering, marker leakage, draft/published confusion). This
ADR removes the *possibility* of a second render pipeline before more surfaces (Responsive,
Revision preview, Theme/AI/Ecommerce builders) are added.

## 4. Terminology

- **Render Surface** — where PB content becomes HTML.
- **Render Authority** — the approved payload source for a surface.
- **Runtime Tree** — normalized `ComponentTree` of instances.
- **Runtime Factory** — `ComponentFactory` (the sole node constructor).
- **View Resolution** — `resolveThemeOrPlugin(kind, type)`.
- **Render context** — the explicit per-render flags (surface, locale, editor-marker mode).
- **Hook boundary** — `RuntimeHookManager` dispatched by `ComponentRuntime`.
- **Asset authority** — the scoped middleware/registry that injects assets.

## 5. Render surfaces (inventory)

| Surface | Entry point | Payload source | Locale | Normalizer | Factory | View resolver | Hooks | Assets | Output | Class |
|---|---|---|---|---|---|---|---|---|---|---|
| Public frontend | `FrontendRenderer::bodyFor` | `published_payload` | `PublicLocaleContext` | LayoutNormalizer | ComponentFactory | resolveThemeOrPlugin | ComponentRuntime | InjectPageBuilderAssets | HTML | CANONICAL_PIPELINE |
| Live editor iframe | `FrontendEditorRenderer` | `draft` (seed `published` first-open) | `?locale=` | LayoutNormalizer | ComponentFactory | resolveThemeOrPlugin | ComponentRuntime | Inject{PageBuilder,FrontendEditor}Assets | HTML | SAFE_ADAPTER |
| Full preview | `EditorPreviewController` | posted payload | `RenderLocale` | LayoutNormalizer (via `state()`) | ComponentFactory | resolveThemeOrPlugin | ComponentRuntime | — | HTML | SAFE_ADAPTER |
| Component preview | `ComponentPreviewController` | posted payload | `RenderLocale` | LayoutNormalizer | ComponentFactory | resolveThemeOrPlugin | ComponentRuntime | — | HTML | SAFE_ADAPTER |
| Structure editor canvas | `EditorPreviewController` (same) | posted payload | `RenderLocale` | " | " | " | " | — | HTML | SAFE_ADAPTER |
| Design-resource preview | `PreviewController` + `PreviewEngine` | design resource (sample/section/block) | app locale | LayoutNormalizer (via renderer) | ComponentFactory | resolveThemeOrPlugin | ComponentRuntime | preview document CSP | HTML doc | SAFE_ADAPTER |

Every surface routes component HTML through `LayoutRenderer` → `ComponentTree` →
`ComponentRuntime`. Controllers render only the **preview document wrapper**
(`page-builder::preview.document`), never component views.

## 6. Canonical pipeline

```text
Effective owner (ADR-OWNER)
  → exact pb_layouts row (ADR-DATA)
  → selected payload authority (published | draft | posted)
  → LayoutRenderer::{render | renderForEditor | renderComponentForEditor}
    → ComponentTree::fromPayload(LayoutNormalizer::normalize, ComponentFactory)
    → ComponentFactory::section/block (+ recursive children via the same factory)
    → ComponentRuntime::render  (boot → resolve lifecycle → view → markers?)
        → RuntimeHookManager (beforeResolve/afterResolve/beforeRender/afterRender)
        → resolveThemeOrPlugin(kind, type) → View::make
        → Localize (value locale) / RenderLocale scope
        → SpacingStyle (presentation)
        → editor marker wrap (editor mode only)
  → HTML
  → asset middleware registers scoped assets
```

**Convergence point:** `LayoutRenderer` (backed by `ComponentTree` + `ComponentRuntime`).
No alternate pipeline exists.

## 7. Payload authority by surface

| Surface | Reads | Never |
|---|---|---|
| Public frontend | `published_payload` | draft |
| Live editor iframe | `draft_payload` (published seed on first-open only) | stale published once a draft exists |
| Full / component preview | posted payload | stored published |
| Design-resource preview | resolved design resource | owner draft/published |

## 8. Normalization authority

`LayoutNormalizer` is the single render-pipeline normalizer (schema `pagebuilder/01`;
keeps only `schema/sections` + node `type/id/data/blocks`; recurses `data.blocks`; drops
unknown keys). `ComponentTree` and `BuilderState` both normalize through it; the render
runtime references no other normalizer (`DesignCaptureNormalizer` reuses its `SCHEMA` in the
capture domain; `DynamicStyleNormalizer` is CSS — neither is in the render pipeline).
Normalization is pure: it returns a new array and never writes.

## 9. Runtime factory / tree

`ComponentFactory` is the sole constructor of `SectionInstance` / `BlockInstance`; it injects
itself so children (section blocks and container/column nested blocks) build through the same
factory. `ComponentTree::fromPayload` = normalize → build. Unknown types still construct an
instance (section → default wrapper; block → nothing), so the runtime never crashes — one
deterministic unknown-type policy. The resolve lifecycle transforms a `resolvedData` **copy**;
the input `data`/payload is never mutated.

## 10. View resolution

One path: `AbstractComponentInstance::resolveThemeOrPlugin(kind, type)` →
`theme::page-builder.{kind}.{type}` (theme override) → `page-builder::{kind}.{type}` (plugin
fallback) → section default / block empty. No component resolves views independently; no
arbitrary `include`. **Traversal-safe:** the fixed `page-builder::{kind}.` namespace prefix +
`View::exists` gate means a crafted payload `type` (`../…`) resolves nothing and falls back —
proven by guard.

## 11. Render context

Render-critical flags are passed explicitly, not inferred from globals:

- payload authority — chosen by the surface (published/draft/posted).
- value locale — `RenderLocale::scope` (editor) / public URL locale; never ambient admin locale.
- editor-marker mode — `ComponentRuntime::setEditorMode(bool)`, scoped to a single render and
  reset in `finally`; passed to views as `$pbEditor`.
- owner/layout identity — fully resolved *before* the runtime begins (ADR-OWNER).

No new context object is introduced: the existing explicit arguments + the transient runtime
flag already satisfy the contract. Forbidden: hidden globals or `request()` deciding
editor/public output inside runtime/Blade (guarded: runtime is `request()`-free).

## 12. Localization boundary

One boundary — `Support\Localize` under one explicit locale (`RenderLocale` for editor,
public URL locale for frontend), per **ADR-PB-TRANSLATABLE-FIELD-REPRESENTATION** (consumed,
not redefined). No component-specific envelope logic; no array-order fallback; same
payload+locale renders identical text on editor and public surfaces.

## 13. Hook boundary

One boundary — `RuntimeHookManager` with four fixed points
(`beforeResolve/afterResolve/beforeRender/afterRender`), dispatched **only** by
`ComponentRuntime` (guarded: `hooks->dispatch(` appears in exactly one file). Ordering is
deterministic (guarded). Callbacks receive the `ComponentInstance` and may mutate its
**runtime** state (`resolvedData` copy, visibility) — never the persisted payload. Blade
files dispatch no lifecycle hooks. The public `cms.content.body` filter is the Core *entry*
seam, not a render-internal hook.

## 14. Asset authority

One authority per scope:

| Scope | Authority |
|---|---|
| Public frontend component CSS + token block | `InjectPageBuilderAssets` (only when render set its request flag) |
| Live editor iframe runtime | `InjectFrontendEditorAssets` (gated: `pb_editor` + `page-builder.edit`) |
| Editor bundles (Filament pages) | `EditorAssets` (versioned URLs) |
| Design-resource preview | `PreviewController` CSP document |

Component views emit **no** `<script>`/`<style>` (guarded). No per-component injection; no
editor bundle leaks to the public frontend.

## 15. Editor marker policy

Markers (`data-pb-component-id/-kind/-type`, `.pb-editor-render-marker`, composition
geometry) are added by `ComponentRuntime::wrapForEditor` **only** when editor mode is on and
the node has a stable id. Public `render()` emits none (guarded). Markers wrap; they never
change component content values or the payload (guarded).

## 16. Cache policy

**The runtime is uncached by design.** No `Cache::`/`remember`/render/HTML/response cache
exists in the render path (guarded). Blade view *compilation* cache is framework-level and
irrelevant to output identity. This absence is locked: no render cache may be introduced
without a follow-up ADR proving owner/layout/version/locale/surface keying and
publish-invalidation.

## 17. Error / fallback policy

| Condition | Public frontend | Editor / preview |
|---|---|---|
| Empty / missing published payload | classic body | classic body / empty canvas |
| Unknown section | default wrapper | default wrapper |
| Unknown block | nothing (debug notice) | nothing (debug notice) |
| Missing view | fallback (default/empty) | same |
| Blade throw | classic body (`FrontendRenderer` try/catch) | reported; degrade to classic |
| Normalization drops bad node | node skipped | node skipped |
| Invalid locale | canonical owner (fail-soft read) | fail-closed 4xx on mutation surfaces |

No silent data mutation; equivalent surfaces share policy.

## 18. Determinism / immutability

Same normalized payload + same render context → identical HTML (guarded). Rendering issues
zero DB writes and never mutates the input payload (guarded). Public never reads draft;
preview never reads stored published (ADR-DATA + guards).

## 19. Security / trust boundary

- View names are namespace-prefixed and `View::exists`-gated; payload `type` cannot traverse
  or name arbitrary views (guarded).
- Component views escape output (`e()` / Blade `{{ }}`); trusted canvas HTML is injected only
  through the dedicated middleware seam, never through sanitized content.
- Runtime reads no `request()` and resolves no owners (guarded) — trust decisions happen at
  the controller/owner-resolution boundary before rendering.

## 20. Core / plugin boundary

No PB runtime logic lives in Core (guarded by the Core-independence scan, ADR-OWNER). Locale
authority is consumed via Core contracts; rendering is entirely plugin-owned.

## 21. Future extension seams

| Capability | Seam | Allowed stage | Forbidden shortcut |
|---|---|---|---|
| Responsive controls | `ResponsiveResolver` (resolve step) | resolveResponsive | a second renderer/breakpoint pipeline |
| Visibility rules | `ConditionResolver` (resolve step) | resolveConditions | Blade deciding visibility ad-hoc |
| Animations | component `data` + view + asset registry | render/asset | per-component `<script>` |
| Dynamic data | `DynamicValueResolver` (resolve step) | resolveDynamicData | `request()`/DB in views |
| Reusable / Global blocks | new owner payload referenced by id, resolved via factory | tree build | inlining a hidden copy into published payload |
| Revision / scheduled-publish preview | new payload authority feeding `LayoutRenderer` | payload selection | a preview-specific renderer fork |
| Theme Builder | theme view overrides (`theme::page-builder.*`) | view resolution | bypassing resolveThemeOrPlugin |
| Loop Builder | `DynamicValueResolver` + factory children | resolve/tree | second tree builder |
| AI-generated blocks | normalized payload → same pipeline | normalization/tree | client-authoritative HTML |
| Ecommerce product/landing blocks | registered block/section definitions | registry + views | a parallel runtime |

All extend approved seams; none introduces an alternate pipeline.

## 22. Architecture guards

`PageBuilderRuntimeArchitectureGuardTest` (16): deterministic render, no payload mutation,
zero DB writes, public has no markers, editor markers wrap-only, nested factory path, unknown-
type determinism, traversal-safe view resolution, single hook boundary + deterministic order,
single render-pipeline normalizer, single runtime factory, preview controllers render no
component Blade, runtime free of request/owner-resolution, component views emit no unmanaged
assets, no render cache. Companion: OWNER + DATA + RenderSource + LocaleAwareRuntime suites.

## 23. Rejected alternatives

Controller renders component Blade directly; component resolves owner/locale; a component
instantiates its own runtime; multiple normalizers in the pipeline; a preview- or frontend-
specific renderer fork; Blade mutating payload; hooks dispatched from multiple layers;
component-emitted unmanaged assets; ambient locale controlling editor output; public reading
draft; preview reading stored published; a hidden HTML cache; client-authoritative HTML.

## 24. Consequences

One render pipeline, one normalizer, one factory, one view resolver, one localization
boundary, one hook boundary, one asset authority per scope — all guarded. No production change
was required; the audit found the architecture already compliant. Future render features
inherit the pipeline or fail the guards.

## 25. Compatibility notes

No Core / schema / migration / public-render change. Output is byte-identical to pre-audit for
the built-in library. Editor-mode markers and `$pbEditor` view flag are unchanged.

## 26. Acceptance checklist

- [x] One canonical render pipeline (`LayoutRenderer`).
- [x] One normalizer (`LayoutNormalizer`).
- [x] One runtime factory / recursive tree (`ComponentFactory`).
- [x] One view-resolution boundary (`resolveThemeOrPlugin`), traversal-safe.
- [x] Explicit render context; no ambient globals / `request()` in runtime.
- [x] One localization boundary (`Localize` + `RenderLocale`).
- [x] One hook boundary (`RuntimeHookManager` via `ComponentRuntime`), deterministic.
- [x] One asset authority per scope; no unmanaged component assets.
- [x] Public uses published only; preview/editor use draft/posted only.
- [x] Runtime read-only; zero DB writes; deterministic HTML.
- [x] Public HTML has no editor markers; markers presentation-only.
- [x] No hidden render cache (absence locked).
- [x] Future features extend approved seams.
- [x] No Core / schema / migration change.
- [ ] Live EN/VI render acceptance (Work R) — pending a booted admin.

Flip Status to **Accepted** once the final box is checked.
