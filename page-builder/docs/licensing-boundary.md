# Licensing / Entitlement Boundary (Phase 26.40A)

> **Type:** Product Architecture. **No production code.** Contracts/specification only.
> Part of the Design Library architecture — see
> [design-library-architecture.md](design-library-architecture.md),
> [design-resource-model.md](design-resource-model.md),
> [design-preview-architecture.md](design-preview-architecture.md),
> [design-package-specification.md](design-package-specification.md),
> [product-decisions.md](product-decisions.md).

**Core rule:** FREE and PRO consume **identical** resources through **one** runtime,
renderer, importer, serializer, package format, preview engine, and library. **Only
entitlement differs. Resources are NEVER duplicated per tier.**

---

## 1. Core Rule & Rationale

There is exactly **one** of each shared thing (see
[design-library-architecture.md §3.4](design-library-architecture.md)); licensing is a
**thin cross-cutting concern**, not a fork of the product.

**Why not duplicate resources per tier (the anti-pattern):**
- **Drift.** A "free copy" and a "pro copy" of the same section inevitably diverge.
- **Double maintenance.** Every fix/update must be applied twice.
- **Breaks one-package-format.** Community/official/premium could no longer share a format.
- **Breaks preview truthfulness.** Two variants means the preview may not match what a user
  actually gets after upgrading.

Instead, a single resource carries an **entitlement classification**, and a single
**resolver** decides access at the right moments.

---

## 2. The `EntitlementResolver` Seam

A single injectable resolver is the **one place tier logic lives**:

```ts
// Illustrative — specification only.
type Entitlement =
  | 'granted'            // may import/install/apply/update
  | 'requires_upgrade'   // FREE → PRO
  | 'requires_purchase'  // premium / marketplace
  | 'expired'            // previously entitled, now lapsed
  | 'unknown';           // resolver has no data

interface EntitlementResolver {
  can(target: { resourceId?: string; packId?: string }, capability: string): Entitlement;
}
```

- It reads a **license/subscription/purchase source** (the data source is out of scope —
  a marketplace/cloud provider fills it later).
- It is consulted for **badging** (non-blocking, cosmetic) and **enforcement** (blocking).
- It never mutates resources and never touches Frozen-Internal state.

---

## 3. Entitlement Metadata = Classification, Not a Gate

Resources/packs carry an `entitlement` tag (`free | pro | premium`) in the Package v2
manifest and on `DesignResourceItem` (see
[design-package-specification.md §2](design-package-specification.md),
[design-resource-model.md §2](design-resource-model.md)).

- This tag is a **classification** used for filtering/badging.
- It is **not** the gate. The gate is `EntitlementResolver` + the enforcement points (§4).
- A resource is authored **once**; its tier is metadata, not a separate artifact.

---

## 4. Where Checks Belong — BADGE vs ENFORCE

The decisive table. For each stage: does entitlement **badge**, **enforce**, or do nothing?

| Stage | Action | Behavior |
|---|---|---|
| **Library / discovery** | **BADGE only** | Premium/pro resources are fully **listed, searchable, and filterable** (including a "Premium" facet) with an upgrade/lock **badge**. **Never hidden. Never duplicated.** (Elementor Pro model.) |
| **Preview** | **BADGE + allow** | Premium resources **are previewable** (runtime preview renders) so users see value before upgrading. The raw importable package/definition/assets stay **gated** — preview ≠ possession. See [design-preview-architecture.md §6](design-preview-architecture.md). |
| **Import / Installation** | **ENFORCE (primary gate)** | `EntitlementResolver.can(...)` is consulted **before** landing resources into `ImportedTemplateDefinitionSource` / registries. Not entitled → **blocked** with an actionable, structured reason (`requires_upgrade` / `requires_purchase`), same shape as compatibility `reasons`. |
| **Apply / Insert into document** | **ENFORCE (defense-in-depth)** | Backstop for an already-installed-but-now-`expired` resource. The gate runs **before** the `insert-pattern` command; it never mutates runtime. Entitled → proceeds through the normal one-command/one-undo path. |
| **Updates** | **ENFORCE** | Entitlement is **re-checked on pack update**. `expired` → no updates, but **already-installed content keeps working** (grandfathering, §4.1). |

### 4.1 Grandfathering policy

Expiry blocks **new** imports/installs/updates of gated resources; it does **not** retroactively
break content the user already inserted. Inserted content is the user's own copy
(copy-on-insert), so it continues to render and edit normally. This keeps entitlement a
gate on *acquisition*, not a kill-switch on *existing work*.

### 4.2 Why enforce at import/install, not discovery/preview

Enforcing at acquisition (not browsing) is what lets premium resources be **fully visible
and previewable** — the proven conversion pattern — while still protecting the importable
asset. It also keeps a single enforcement choke-point (the importer) rather than scattering
checks across the UI.

---

## 5. PHP Attach Points (no Frozen-Internal changes)

Entitlement hooks onto **Public** seams only (Phase 26.40); it never touches CommandBus,
BuilderState, or serialization.

**(a) Service-provider registration gating** — only register premium components when licensed:

```php
// Illustrative — in a plugin/theme ServiceProvider (NOT implemented this phase).
if (app(EntitlementResolver::class)->can(['packId' => 'acme.pro'], 'register')->isGranted()) {
    PageBuilder::registerSection(new PremiumHeroSection());
}
```

**(b) `RuntimeHookManager::beforeResolve`** — render-time backstop for premium components:

```php
PageBuilder::runtime()->on('beforeResolve', function (ComponentInstance $instance) {
    // e.g. flag/hide a premium component instance if entitlement lapsed — advisory, never
    // mutating BuilderState or the command pipeline.
});
```

**(c) Pattern/Template discovery filters** — attach the `entitlement` badge metadata during
discovery so the library can badge without hiding.

**(d) Importer gate** — the primary enforcement point (§4), consulted inside the existing
validation-first/atomic importer before resources are landed.

> None of these change the frozen runtime. (a)–(c) are registration/hook/metadata seams;
> (d) is a gate *before* the existing import lands anything.

---

## 6. Tiers & Sources

FREE, PRO, and marketplace/premium map onto sources; the **format is identical**, only the
resolver's trust/entitlement policy differs (see
[design-resource-model.md §2.2](design-resource-model.md),
[design-package-specification.md §2.2](design-package-specification.md)):

| Source / tier | Package | Signature | Entitlement policy |
|---|---|---|---|
| **Community** | Package v2 | self-signed / optional (advisory) | typically `free`; resolver `granted` |
| **Official / builtin** | Package v2 | vendor-signed | `free` or bundled with edition |
| **Premium / marketplace** | Package v2 | seller/vendor-signed (required-configurable) | `requires_purchase` until entitled |
| **Offline (`imported`)** | Package v2 / v1 | may be unsigned (advisory) | still resolver-checked at install |
| **Cloud** (future) | Package v2 | provider policy | provider-supplied entitlement |

Same library, same package, same preview, same insert path — the resolver differentiates.

---

## 7. Marketplace Readiness & Future

The boundary supports the roadmap **without redesign**:

- **Commercial sellers:** premium packs are signed Package v2 with `entitlement`; the
  resolver gates import/install; a storefront is a future `EntitlementResolver` **data
  source** + a `marketplace` distribution provider.
- **Offline packs:** imported through the existing atomic importer; resolver-checked at
  install like any other source.
- **Cloud packs:** reserved `cloud` source; a cloud provider supplies entitlement to the
  resolver.
- **Auto-updates:** updates re-run the validation-first importer and **re-check
  entitlement**; grandfathering protects existing content (§4.1).
- **AI-generated pages:** AI output is just a `DesignResource` (an id-free tree) → enters
  via the **same** import/preview/insert path and the **same** entitlement rules. No special
  case, no bypass.

**Out of scope (this phase):** payment/purchase flow. It is modeled only as a resolver data
source and a reserved source key.

---

## 8. Non-Goals (This Phase)

- No implementation; no payment integration; no new runtime surface.
- `EntitlementResolver` and the `entitlement` tag are **design contracts only**.
- No resource duplication under any circumstance — the single-resource, single-format,
  single-library invariant is the whole point.

---

## 9. Summary

| Principle | Decision |
|---|---|
| One architecture | FREE & PRO share runtime/renderer/importer/serializer/package/preview/library |
| Entitlement | A single `EntitlementResolver`; tag on resources is classification, not a gate |
| Discovery | **Badge**, never hide, never duplicate |
| Preview | **Previewable** + badged; importable source stays gated |
| Import / Install | **Enforce** (primary gate); structured upgrade/purchase reasons |
| Apply / Insert | **Enforce** backstop before `insert-pattern`; never mutates runtime |
| Updates | **Enforce**; grandfather already-installed content |
| Attach points | Registration gating · `RuntimeHookManager` · discovery filters · importer gate |
| Marketplace/AI/cloud | Ready via reserved sources + resolver data source; no redesign |

---

*End of licensing-boundary.md*
