# ADR-PB-COMPONENT-DOMAIN-ARCHITECTURE — One Component Model

**Status:** Accepted (architecture lock; automated gates GREEN + live EN/VI operator acceptance recorded; frozen at PB-CE-FREEZE-2R)
**Date:** 2026-08-06
**Phase:** PB-CE-FREEZE-1 / Stage 6
**Depends on (frozen):** ADR-PB-RUNTIME-RENDERING-ARCHITECTURE, ADR-PB-DATA-ARCHITECTURE, ADR-PB-TRANSLATABLE-FIELD-REPRESENTATION.

---

## 1. Status

Accepted on the Community freeze. `PageBuilderComponentArchitectureGuardTest` (4/640) +
full PB suite green; live EN/VI operator acceptance recorded. Frozen at PB-CE-FREEZE-2R.

## 2. Context

Sections and blocks are the Page Builder's authored units. Before feature growth
(Global Blocks, AI blocks, Ecommerce blocks) their model must be single: one contract,
one base, one registry pair, one lifecycle, one capability model, one extension seam.

## 3. Terminology

- **Definition** — the design-time description of a section/block (`ComponentDefinition`).
- **Instance** — the render-time object (`SectionInstance`/`BlockInstance`).
- **Registry** — the type→definition store (`SectionRegistry`/`BlockRegistry`).
- **Capability** — a `supports()` flag (e.g. allowed child block types).

## 4. Component model

```text
ComponentDefinition (interface)
  ← AbstractComponentDefinition (base: safe defaults + canonical toArray)
      ← AbstractSectionDefinition / AbstractBlockDefinition
          ← concrete Hero/Text/Heading/Button/Container/Column/…
  → AbstractRegistry (SectionRegistry | BlockRegistry): type → flattened array
  → ComponentFactory: array → SectionInstance | BlockInstance (Runtime, ADR-RUNTIME)
  → AbstractComponentInstance lifecycle: hydrate → resolve* → render
  → resolveThemeOrPlugin(kind, type): view
```

## 5. Component contract

`ComponentDefinition` declares: `type`, `label`, `category`, `icon`, `defaults`, `schema`
(field tabs the editor renders), `supports` (capabilities), `assets`
(`css/js/editor_css/editor_js`), `editor` (insert/preview metadata), `toArray`. Guarded:
every concrete definition implements the contract, extends the one base, and produces the
canonical nine-key shape with the four-key asset map.

## 6. Registry

`SectionRegistry` and `BlockRegistry` both extend `AbstractRegistry` (one store contract:
`register/get/has/all/types/count/forget/flush`). Registration accepts an array,
a `ComponentDefinition` object, or its class-string (flattened via `toArray`). The built-in
library (`DefaultLibrary`) registers **9 sections** + **10 blocks** through this one pair.
The runtime factory looks types up here — the sole component lookup path.

## 7. Component lifecycle

`AbstractComponentInstance`: `hydrate` → `resolveDefaults` → `resolveResponsive` →
`resolveTokens` → `resolveDynamicData` → `resolveConditions` → render. Each step transforms
a `resolvedData` **copy**; the input `data`/payload is never mutated (ADR-RUNTIME §9).
Resolvers are reached through the `ComponentFactory`, not instantiated ad-hoc.

## 8. Capability model

Capabilities are declared via `supports()` (e.g. container/column expose a child-blocks
capability; sections expose alignment/child policy). Children are built through the same
`ComponentFactory` (section `blocks[]`; container/column `data.blocks[]`). One capability
model; one recursive child path.

## 9. Extension seam

New components register a `ComponentDefinition` (or array) through `Section/BlockRegistry`
(exposed via `PageBuilderManager::registerSection/registerBlock`) and ship a
`page-builder::{kind}.{type}` view (theme override supported). No other extension path. The
client extension API is frozen separately (`ExtensionApiFreeze.test.ts`).

## 10. Component inventory

| Kind | Types |
|---|---|
| Section (9) | generic, hero, text-section, image-section, columns, cta, faq, testimonial, logo-strip |
| Block (10) | text, heading, button, image, icon, accordion-item, container, column, logo, quote |

All via `AbstractComponentDefinition`; container/column carry the nested-children capability;
translatable fields are per-locale envelopes (ADR-PB-TRANSLATABLE-FIELD-REPRESENTATION).

## 11. Forbidden patterns

A definition/component must never: resolve the owner, resolve the request locale, read
`request()`, persist data (`LayoutStore`/`DB::`/`->save`), inject unmanaged assets, render
Blade directly (`View::make`), bypass the registry/factory, or mutate the payload during
render. Guarded by a source scan of `src/Definitions` + the runtime guards.

## 12. Guards

`PageBuilderComponentArchitectureGuardTest`: one contract (all definitions implement it +
extend the base), canonical shape, single populated registry pair, forbidden-boundary scan.
Plus ADR-RUNTIME guards (single factory, nested-factory path, view resolution).

## 13. Consequences

One component model; new components extend the seam or fail the guards. No production change
was required — the audit found the model already single.

## 14. Acceptance checklist

- [x] One contract (`ComponentDefinition`).
- [x] One base + one registry pair.
- [x] One lifecycle + one factory (ADR-RUNTIME).
- [x] One capability model (`supports`).
- [x] One extension seam.
- [x] Forbidden boundary guarded.
- [ ] Live acceptance (Community freeze).
