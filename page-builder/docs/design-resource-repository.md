# Design Resource Repository (Phase 26.42)

> **Status:** Implemented. Backend infrastructure only — no UI, no cloud, no tree/
> serialization change. It is the single source of truth for Design Resources and
> the foundation every future consumer (Library UI, import, distribution, preview)
> resolves through.
>
> Implements the resource model of [design-resource-specification.md](design-resource-specification.md)
> and [design-resource-schema.md](design-resource-schema.md) (26.40B) as a runtime
> PHP layer. The 26.41 Runtime Preview now consumes it.

---

## 1. Audit — resource loading before this phase

Before 26.42, resource lookup was **scattered and consumer-owned**:

| Consumer | How it loaded resources |
|---|---|
| `PreviewResourceResolver` (26.41) | Directly read `SectionRegistry` / `BlockRegistry` and `SampleLayout`, and hand-built payloads. |
| Patterns | `PatternRegistry` discovers pattern files independently. |
| Template Library (TS) | A separate, editor-side discovery stack with no PHP counterpart. |

Problems: no common identity (RID), no single resolver, duplicated wrapping logic,
no conflict policy, no query surface, no caching. This phase centralises all of it.

---

## 2. What the repository provides

- **One identity:** every resource has a RID `<source>:<kind>:<resourceId>`.
- **One resolver:** `find(rid)` / `query(...)` — consumers never scan registries or
  packages themselves.
- **Immutable resources:** `DesignResource` is a read-only object.
- **Pluggable providers:** sources register themselves; the repository aggregates
  and de-conflicts them deterministically.
- **A query engine:** filter by kind, source, vendor, package, category, tags,
  version, and free-text search — with deterministic ordering.
- **Automatic caching:** an in-memory index rebuilt only when a provider changes.

---

## 3. Types (`src/Repository/`)

| Type | Role |
|---|---|
| `Rid` | Value object for `<source>:<kind>:<resourceId>`. Validates (kind ∈ block/section/page/kit; slug source/id). `parse()`, `isValid()`, `toString()`. |
| `DesignResource` | Immutable resource: identity (C1), classification (C2), descriptive (C3), payload (C4, id-free renderable), preview (C5), compatibility (C7), distribution/licensing (C8). No file paths. |
| `ResourceQuery` | Immutable, composable filter + ordering. `whereKind/whereSource/whereVendor/whereCategory/wherePackage/whereVersion/withTag/search/orderBy`. |
| `ResourceCollection` | Immutable, ordered, RID-keyed result set. `all/first/rids/filter/map/sort/count/toMetadataArray`. |
| `Contracts\DesignResourceRepositoryInterface` | `registerProvider / has / find / tryFind / all / query / refresh`. |
| `Contracts\DesignResourceProviderInterface` | `key / priority / isAvailable / revision / all / find`. |
| `DesignResourceRepository` | The aggregation + conflict + cache implementation. |
| `Providers\LocalResourceProvider` | Built-in sections/blocks + sample page. |
| `Providers\PackageResourceProvider` | Registration seam for imported packages. |
| `Exceptions\*` | `RepositoryException` (base), `ResourceNotFoundException`, `DuplicateRIDException`, `ProviderUnavailableException`, `InvalidResourceIdentifierException`. |

---

## 4. RID resolution

```
official:page:agency-home
community:section:hero-modern
premium:block:pricing-card
builtin:section:hero
```

`find(rid)` looks the RID up in the aggregated index (built from all available
providers). Missing → `ResourceNotFoundException`. Malformed RID → `Rid::parse`
throws `InvalidResourceIdentifierException`.

Because the RID embeds `source`, resources from different channels never collide
(`builtin:section:hero` ≠ `official:section:hero`). Genuine collisions only occur
when two providers claim the exact same RID (§6).

---

## 5. Providers & precedence

Providers register with `registerProvider()` (idempotent by `key()`), and each
declares a `priority()` — **lower number = higher precedence**.

| Provider | key | priority | Serves |
|---|---|---|---|
| `LocalResourceProvider` | `local` | 100 | `builtin:*` (registered sections/blocks + `builtin:page:sample`) |
| `PackageResourceProvider` | `package` | 50 | imported resources (`official/community/premium:*`) |

Future providers (remote, cloud, enterprise) register the same way with their own
priority — no repository change required.

---

## 6. Conflict policy (deterministic)

Providers are ordered by `(priority ASC, key ASC)`. Building the index:

1. The first provider (in that order) to claim a RID **owns** it.
2. A later provider of **different** priority claiming the same RID is **skipped**
   (higher-precedence owner wins — documented, silent).
3. A provider of **equal** priority claiming an already-owned RID is an **ambiguous
   collision** → `DuplicateRIDException`.
4. A single provider yielding a duplicate RID is likewise a `DuplicateRIDException`.

`PackageResourceProvider::register()` additionally rejects a conflicting duplicate
at registration time (same RID, different payload) and is **idempotent** for a
byte-identical resource.

---

## 7. Caching & invalidation

- The index (`rid → DesignResource`) is built **lazily** and cached in memory.
- Each provider exposes a cheap `revision()` token. The repository caches the index
  against the **sum of provider revisions**; when any provider changes its resource
  set, the sum changes and the index is **rebuilt automatically** — no manual
  invalidation. `LocalResourceProvider::revision()` derives from registry counts;
  `PackageResourceProvider` bumps on register/forget/flush.
- Cached `DesignResource` objects are immutable, so sharing them is safe (a repeated
  `find()` returns the same instance).
- The cache is **in-memory only** — no persistent storage. `refresh()` exists for
  explicit clearing but is rarely needed.

Determinism: the same provider set produces the same index and the same query
ordering (default order is RID ascending) on every build.

---

## 8. Query engine

```php
$repo->query(
    ResourceQuery::make()
        ->whereKind('section')
        ->whereSource('community')
        ->withTag('hero')
        ->search('modern')
        ->orderBy(ResourceQuery::ORDER_NAME)
); // → ResourceCollection
```

All filters are AND-combined; tag filters require ALL listed tags. Ordering is
deterministic (ties broken by RID).

---

## 9. Package integration (seam)

The future package importer does **not** write to the repository index. After a
package passes validation it calls `PackageResourceProvider::register($resource)`
for each resource; they become discoverable through the repository immediately
(auto-invalidation). This keeps the importer decoupled from resolution/caching.

---

## 10. Preview integration

`PreviewResourceResolver` (26.41) was refactored to consume the repository instead
of scanning registries:

- `source=sample` → `find('builtin:page:sample')`
- `source=section, ref=X` → `find('builtin:section:X')`
- `source=block, ref=X` → `find('builtin:block:X')`
- `source=rid, ref=R` → `find(R)` (Design-Library friendly)

The **PreviewEngine is unchanged** — it still consumes a `PreviewContext`. Preview
output is byte-identical to 26.41 (the `LocalResourceProvider` materialises the same
renderable payloads the resolver used to build inline).

---

## 11. What it does NOT do (frozen)

- No change to the runtime document model, serialization, `BuilderState`,
  `CommandBus`, or the Extension API.
- No persistent storage; no network/cloud (cloud remains a reserved future provider).
- No UI. This is infrastructure consumed by the (future) Design Library UI, import,
  and distribution layers.

---

## 12. Tests

`tests/Feature/Repository/DesignResourceRepositoryTest.php` (11 tests) covers RID
resolution, not-found/invalid-RID errors, deterministic ordering, query by
kind/source/tag/search, immutable cache reuse, package registration +
auto-invalidation, idempotent + conflicting duplicate registration, equal-priority
collision, priority resolution, and preview-resolver-through-repository. The 26.41
preview suite continues to pass unchanged.

> **Local test note:** move `storage/app/tncms-installed` aside while running plugin
> tests (see [runtime-preview.md §10](runtime-preview.md)); CI runs marker-free.

```bash
php artisan test tests/Feature/Repository/DesignResourceRepositoryTest.php
```

---

*End of design-resource-repository.md*
