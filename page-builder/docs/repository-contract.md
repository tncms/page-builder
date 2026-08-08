# Repository Contract (Phase 26.49A, Rule 7)

> **Status: FROZEN.** Official type:
> `Plugins\PageBuilder\Contracts\RepositoryContract`
> (extends the pre-existing `Repository\Contracts\DesignResourceRepositoryInterface`).
> Backing implementation: `Repository\DesignResourceRepository`. There is exactly
> **ONE** repository.

## Responsibilities

The repository is **READ-oriented**. It only:

- **aggregates** every registered provider into one deterministic index,
- **resolves** a RID to a resource (`has` / `find` / `tryFind`),
- **queries** (`query(ResourceQuery)`) and **searches**,
- **composes** providers, applying a deterministic conflict policy.

## The repository MUST NOT

- normalize
- validate
- capture
- mutate any payload

Those responsibilities belong exclusively to the
[Capture Pipeline](capture-pipeline-contract.md).

## Surface

```php
interface DesignResourceRepositoryInterface
{
    public function registerProvider(DesignResourceProviderInterface $provider): void;
    public function has(string $rid): bool;
    public function find(string $rid): DesignResource;      // throws ResourceNotFoundException
    public function tryFind(string $rid): ?DesignResource;
    public function all(): ResourceCollection;
    public function query(ResourceQuery $query): ResourceCollection;
    public function refresh(): void;
}
```

## Conflict policy (deterministic)

- Providers are ordered by `(priority ASC, key ASC)`; lower priority wins.
- The first provider (in that order) to claim a RID owns it.
- A later provider of **different** priority claiming the same RID is skipped.
- A provider of **equal** priority claiming an already-owned RID is an ambiguous
  collision → `DuplicateRIDException`.

## Caching

The index is built lazily and cached in-memory (never persisted). It rebuilds
automatically when any provider's `revision()` changes (the repository compares the
sum of provider revisions). Cached `DesignResource` objects are immutable, so
sharing them is safe.

## Rules

- The repository is unique — no second repository (Rule 17).
- Consumers (preview, Library, import, distribution) go through the repository;
  they never scan registries, package files or providers directly.
