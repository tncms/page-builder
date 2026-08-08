# Community Synchronization Protocol (Phase 26.50C)

> **Status: Canonical transport layer over the FROZEN platform.** This phase defines
> how Community Repositories exchange **metadata**. It introduces **no downloads, no
> uploads, no publishing, no payload transfer, no package installation, no dependency
> resolution, no marketplace, no accounts, no ratings and no reviews** (Rule 16).
> **Synchronization moves metadata; publishing creates it — never mixed.**

## 1. Where synchronization sits

```
Community Repository
  → Repository Index
    → Synchronization Service
      → Synchronization Cache
        → Community Provider
          → Repository → Registry → Library
```

Publishing (Phase 26.50B) produces the Repository Index. Synchronization only
transports it. Downloads and installation are a later phase and are **out of scope**.

## 2. The protocol at a glance

```php
$svc = app(CommunitySynchronizationService::class);

// Transport-neutral fetch (filesystem, mirror, portable package, HTTP, …):
$result = $svc->sync('community', $source);           // RepositoryIndexSource

// Or synchronize an already-fetched index:
$result = $svc->synchronize('community', $remoteIndex);

$result->added;      // new resources
$result->updated;    // new fingerprint or version
$result->deprecated; // status → deprecated
$result->archived;   // tombstoned as archived
$result->removed;    // gone upstream
$result->unchanged;  // identical
$result->conflicts;  // immutable-version divergence (reported, never merged)
$result->rejected;   // metadata that failed validation (never cached)
```

## 3. Components

| Component | Responsibility |
| --- | --- |
| `RepositoryIndex` / `RepositoryIndexEntry` | The immutable, payload-free metadata projection a repository publishes. |
| `Contracts\RepositoryIndexSource` | The only "fetch" seam. Transport-neutral — no HTTP assumptions (Rule 12). |
| `CommunitySynchronizationService` | Fetch → compare → detect change → report conflicts → refresh cache → record history. |
| `SynchronizationResult` | The deterministic six change classes plus conflict/rejected reports. |
| `SynchronizationCache` | Offline-ready local cache: index + meta + history. Never payload. |
| `SynchronizationHistory` | Append-only record of each run (history, not an audit log). |

## 4. Change detection (deterministic, payload-free)

For each remote entry, classified by `rid`, `fingerprint`, `version`, `status` and
presence only (never payload — Rule 5):

- **added** — rid present remotely, absent from the cache.
- **updated** — rid in both, a new fingerprint or version.
- **deprecated** — rid in both, remote status is `deprecated`, cache status was not.
- **archived** — a remote tombstone with status `archived`.
- **removed** — a remote tombstone, or (full index only) a cached rid absent upstream.
- **unchanged** — rid in both, identical fingerprint, version and status.

See [repository-index.md](repository-index.md) for the entry shape and
[conflict-policy.md](conflict-policy.md) for conflict and rejection handling.

## 5. Full vs incremental (Rule 6)

- **Full** — a complete snapshot of the repository's exposed state. Absence is a
  removal.
- **Incremental** — a delta of changed entries plus explicit tombstones. Absence is
  **not** a removal; only tombstones remove.

The cache always persists a **full** snapshot of the current known state, whichever
mode was synchronized.

## 6. Reuse across editions (Rule 15)

The protocol is the **canonical transport layer** for Community, Marketplace,
Enterprise and PRO providers. Only the transport (`RepositoryIndexSource`) and
authorization differ; the index, cache, change detection, conflict policy and history
are identical.

## 7. Explicitly out of scope (Rule 16)

No downloads, payload transfer, package installation, dependency resolution,
marketplace, accounts, ratings or reviews. Transport of metadata only.
