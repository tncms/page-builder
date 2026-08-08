# Discovery Service (Phase 26.50D)

`CommunityDiscoveryService` queries, filters, sorts and paginates the catalog over the
ONE repository (Rule 6). It never mutates, publishes or synchronizes resources, and it
never stores payload.

## 1. API

```php
$svc = app(CommunityDiscoveryService::class);

$result = $svc->discover(
    DiscoveryQuery::make()
        ->whereKind('page')
        ->whereCategory('saas')
        ->inCollection('starter')
        ->inState(CatalogMetadata::STATE_FEATURED)
        ->compatibleWithTheme('twenty')
        ->requiresDependency('ecommerce')
        ->search('landing')
        ->sortBy(DiscoverySort::Newest)
        ->paginate(1, 24),
);

$result->items();   // CatalogMetadata[] (this page)
$result->total;     // across all pages
$result->pageCount();

$svc->collections();      // registered collections
$svc->categories('page'); // distinct categories for a kind
$svc->featured('page', 6);// curated featured resources
$svc->facets('page');     // { categories, collections, tags, states }
```

`refine(iterable $resources, DiscoveryQuery)` runs the same pure pipeline over a given
resource set — used by the Design Library to layer discovery on an already-filtered
collection.

## 2. Filters (AND-combined)

kind · source · category · collection · tags · curated state · metadata search ·
builder version · theme · required dependency. Compatibility filters are **resolution-
free** (literal / wildcard matching only — Rule 9); dependencies are never resolved.

## 3. Sorting (Rule 8)

`DiscoverySort`: `featured`, `newest`, `updated`, `name`, `category`. Every order is
**total and deterministic** — ties fall back to name then rid. There is **no popularity
or algorithmic ranking**; `featured` reads the curated flag + sort weight, `newest` /
`updated` read curated timestamps.

## 4. Pagination

`paginate($page, $perPage)`; `perPage = 0` (default) returns all matches. `DiscoveryResult`
exposes `total`, `page`, `perPage`, `pageCount()` and `hasMore()`.

## 5. Guarantees

- Deterministic over the same catalog (Rule 8).
- Metadata only — never inspects or returns payload (Rule 1).
- Provider-agnostic — works above Community, Marketplace, Enterprise and PRO (Rule 10).
- Read-only — never mutates, publishes or synchronizes (Rule 6).
