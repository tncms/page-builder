# Design Marketplace & Licensing Metadata (Phase 26.40B)

> **Type:** Technical Specification (normative). **No production code.**
> Specifies **marketplace metadata** (Step 14) and **licensing/commercial metadata**
> (Step 10) — the C8 "distribution" layer. The overriding rule: **this metadata MUST NOT
> affect runtime, payload, or resource equality.** FREE, PRO, community, and premium copies
> of a design are the **same resource** (C1–C7); only this layer differs.
>
> Related: [licensing-boundary.md](licensing-boundary.md) (enforcement model, 26.40A),
> [design-resource-schema.md §8](design-resource-schema.md),
> [design-package-manifest.md §10–§11](design-package-manifest.md),
> [design-version-compatibility.md](design-version-compatibility.md).
>
> **Conventions:** RFC 2119 keywords. All KEYS invariant; display strings localizable.

---

## 1. Isolation guarantee (the prime directive)

Normative invariants for everything in this document:

1. **No runtime influence.** Marketplace/licensing metadata MUST NOT be read by the runtime
   to alter rendering or insertion.
2. **Excluded from identity/equality.** C8 is excluded from a resource's canonical
   serialization; two resources differing only in C8 are byte-equal (resource-spec §7).
3. **No resource duplication.** A design is authored once; tiers/pricing are metadata, never
   separate artifacts.
4. **Round-trip stable.** `import(export(R))` preserves C1–C7 regardless of C8
   (import-export §B.6).
5. **Tolerant reader.** Unknown optional marketplace fields MUST be ignored, not rejected
   (version-compat §5), so the marketplace schema can evolve independently.

---

## 2. Licensing / commercial metadata (Step 10)

Carried at resource level (`distribution`) and/or package level (`manifest.marketplace` +
`manifest.license`).

```ts
interface CommercialMetadata {
  entitlement: 'free' | 'pro' | 'premium'   // CLASSIFICATION (badge), not a gate
  licenseId: string                          // KEY (SPDX-ish): "MIT" | "commercial" | "cc-by-4.0" | …
  visibility?: 'public' | 'unlisted' | 'private'  // discovery visibility; default "public"
  pricing?: PricingMetadata                  // §3
  trial?: TrialMetadata                      // §4
  redistributable?: boolean
  supersededBy?: string                      // RID pointer (deprecation, version-compat §7)
  deprecated?: boolean
}
```

- **`entitlement`** classifies for **badging**; the gate is the `EntitlementResolver` at
  import/install/update (licensing-boundary §4). This field MUST NOT itself block anything.
- **`licenseId`** is legal/descriptive; pairs with `manifest.license` (full text/url/file).
- **`visibility`** controls whether a resource is *listed*, not whether it *renders* —
  `private`/`unlisted` affect discovery surfaces only.

### 2.1 Relationship to enforcement

This document defines **data**; enforcement is defined in
[licensing-boundary.md](licensing-boundary.md):
- **Badge** at discovery + preview (premium always listed + previewable).
- **Enforce** at import / install / apply / update via the resolver.
- **Grandfather** already-installed content on expiry.

The metadata here is the input the resolver and the badge UI consume.

---

## 3. Pricing metadata

```ts
interface PricingMetadata {
  model: 'free' | 'one-time' | 'subscription' | 'bundle'
  currency?: string          // ISO-4217, e.g. "USD"
  amount?: number            // minor units (e.g. cents); integer
  period?: 'month' | 'year'  // for subscription
  sku?: string               // seller/marketplace SKU KEY
  bundleOf?: string[]        // RIDs included (model = "bundle")
}
```

- Pricing is **descriptive display data** for the marketplace surface; the actual purchase
  transaction is **out of scope** (a future `EntitlementResolver` data source / storefront).
- `amount` in integer minor units avoids float drift; `currency` REQUIRED when `amount`
  present.
- Pricing MUST NOT gate preview or discovery; it informs the upgrade/purchase call-to-action.

---

## 4. Trial metadata

```ts
interface TrialMetadata {
  type: 'none' | 'time' | 'usage' | 'preview-only'
  durationDays?: number      // type = "time"
  maxUses?: number           // type = "usage"
}
```

- Describes how a premium resource MAY be sampled. `preview-only` (the default posture) means
  it is fully previewable but not importable without entitlement (the Elementor Pro model).
- Trial enforcement, if any, is the resolver's job; this is declarative metadata only.

---

## 5. Marketplace metadata (Step 14)

Channel/marketplace-supplied metadata, typically at package/vendor level. **Authoritative
values are set by the marketplace**, not self-asserted by producers.

```ts
interface MarketplaceMetadata {
  schemaVersion: string       // marketplace metadata schema version (independent axis)
  listingId?: string          // marketplace listing KEY
  vendor?: ResourceVendor     // seller identity (mirror; authoritative verification below)
  verified?: boolean          // marketplace-verified seller/pack (authoritative)
  ratings?: RatingSummary     // §5.1 — marketplace-owned, not producer-owned
  downloads?: number          // marketplace-owned counter
  publishedAt?: string        // ISO-8601 (input, not runtime-generated)
  updatedAt?: string          // ISO-8601
  categoriesExtra?: string[]  // marketplace taxonomy KEYS (in addition to resource category)
  supportUrl?: string         // safe URL
  changelogUrl?: string       // safe URL
}
interface RatingSummary { average: number; count: number; histogram?: number[] }
```

### 5.1 Trust boundary for marketplace-owned fields

- `verified`, `ratings`, `downloads` are **marketplace-authoritative**. When a package is
  imported offline, these fields are **advisory/self-asserted** and consumers SHOULD label
  them as unverified (do not display a trust badge from self-asserted data).
- Consumers MUST treat missing marketplace metadata as neutral (no rating, not verified),
  never as an error.

---

## 6. Determinism & packaging

- All timestamps (`publishedAt`, `updatedAt`) are **inputs**, never `now()` (import-export
  §B.2). Marketplace counters (`downloads`, `ratings`) are **excluded from export
  canonicalization** when producing a reproducible pack — they are channel state, not pack
  content, and MUST NOT change the pack's checksum/signature.
- Because C8 is excluded from resource identity/equality, marketplace updates (new ratings,
  price changes) do not alter the underlying resource digests or require re-signing the
  resource payloads.

---

## 7. Localization

- Display strings within this layer (e.g. a pricing label, a marketplace category label) are
  localizable via the standard `LocalizationTable`. All KEYS (`entitlement`, `model`,
  `licenseId`, category KEYS) remain invariant.

---

## 8. Marketplace readiness (recap)

This metadata makes the format seller-ready without building a storefront:
- **Community** packs: `entitlement:free`, self-asserted marketplace fields (advisory).
- **Official** packs: verified vendor, free/bundled entitlement.
- **Premium/commercial** packs: pricing/trial + `entitlement:premium`; resolver gates
  acquisition.
- **Offline** packs: same format; marketplace fields advisory; still resolver-checked.
- **Cloud** (future): a cloud channel supplies authoritative marketplace metadata + entitlement.

No redesign is needed to add a marketplace: it is a new source/channel + an
`EntitlementResolver` data source. Payments remain out of scope
([product-decisions.md §4.3](product-decisions.md)).

---

## 9. Conformance checklist

1. C8 metadata never influences runtime/payload/equality; excluded from resource identity (§1).
2. `entitlement` is classification; enforcement is the resolver's at import/install (§2, §2.1).
3. Pricing/trial are descriptive; purchase is out of scope; no gating of preview/discovery (§3–§4).
4. Marketplace-owned fields (`verified`/`ratings`/`downloads`) are authoritative only from the
   marketplace; advisory offline (§5.1).
5. Timestamps are inputs; marketplace counters excluded from reproducible packaging (§6).
6. Tolerant reader for unknown optional fields; KEYS invariant; display strings localizable (§1, §7).

---

*End of design-marketplace-metadata.md*
