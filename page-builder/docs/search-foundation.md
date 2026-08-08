# Search Foundation (Phase 26.50D)

Discovery implements **metadata search only** (Rule 7). There is **no full-text engine,
no AI search and no semantic search** — those are explicitly out of scope.

## 1. Searched fields

`DiscoveryQuery::search($term)` matches, case-insensitively, against a resource's
catalog metadata:

- title (name)
- description
- category
- collections
- tags
- author (from curated `display.author`)

A blank term matches everything. A non-blank term matches when the needle appears as a
substring of any field above (`mb_stripos`). Matching is deterministic and never
inspects payload.

## 2. Combining with filters

Search is AND-combined with every other filter:

```php
DiscoveryQuery::make()
    ->whereKind('page')
    ->inCollection('saas')
    ->search('fintech');
```

## 3. Explicitly NOT implemented (Rule 7)

- No inverted index / full-text engine.
- No relevance scoring or ranking beyond the deterministic sort orders.
- No AI or vector / semantic search.

These remain available to a future search phase; the foundation here is a simple,
predictable metadata substring match that every provider (Community, Marketplace,
Enterprise, PRO) shares.
