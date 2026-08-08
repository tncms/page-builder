# Community Discovery & Catalog (Phase 26.50D)

> **Status: Presentation & indexing layer over the FROZEN platform.** This phase turns
> the Community Repository from a technical index into a user-facing **discovery**
> system. It introduces **no user accounts, ratings, reviews, reputation, moderation,
> verified authors, payments, marketplace, licensing or commerce** (Rules 1, 12, 16).

## 1. Where discovery sits

```
Published Resource → Repository Index → Catalog Metadata
  → Discovery Service → Design Library
```

Discovery is a **presentation and indexing layer** — it MUST never become another
repository (Rule 1). It references resources by RID and reads their manifest metadata;
it stores **no payload** and duplicates no resource storage.

## 2. Components

| Component | Responsibility |
| --- | --- |
| `CatalogMetadata` | Additive per-resource catalog projection (category, collections, tags, curated states, sort weight, display). Never mutates identity. |
| `Collection` | A curated grouping descriptor ("Starter Designs", "SaaS Templates"). Membership is additive metadata. |
| `DiscoveryQuery` | Immutable, composable query: filters, metadata search, compatibility filters, sort, pagination. |
| `DiscoveryResult` | Immutable, paginated result (metadata only). |
| `DiscoverySort` | Deterministic sort orders: featured, newest, updated, name, category. |
| `CommunityDiscoveryService` | Query / filter / sort / paginate / combine catalog metadata over the ONE repository. |

## 3. Provider-agnostic (Rule 10)

Discovery operates over the ONE `DesignResourceRepository`, which already aggregates the
Community, Marketplace, Enterprise and PRO providers. Every provider exposes the same
`DesignResource` shape, so discovery works **above all of them uniformly** — Marketplace,
Enterprise and PRO reuse this discovery layer verbatim. Curation (collections + per-rid
overlay) is injected data, not stored resources.

## 4. What it never does (Rules 6, 12, 16)

- Never mutates, publishes or synchronizes resources.
- Never stores payload or becomes a second repository.
- No accounts, ratings, reviews, reputation, moderation or verified authors.
- No marketplace, licensing, payments or commerce.

## 5. Design Library integration (Rule 11)

The Design Library gains a **Community tab** with search, categories, collections,
featured resources and compatibility filters. Preview, Insert, Use Template and
Availability are unchanged — discovery only orders and filters which cards are shown.
See [discovery-service.md](discovery-service.md), [catalog-model.md](catalog-model.md),
[collections.md](collections.md) and [search-foundation.md](search-foundation.md).
