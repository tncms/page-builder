# Offline Synchronization Cache (Phase 26.50C)

The `SynchronizationCache` is the local, **offline-ready** store of synchronized
metadata (Rules 7 & 9). Once a repository has been synchronized, its metadata remains
usable with **no network** — the cache is plain local files.

## 1. What it stores (Rule 7)

Per repository, exactly three metadata documents — and **never** payload:

| File | Content |
| --- | --- |
| `index.json` | The last synchronized `RepositoryIndex` (metadata only). |
| `meta.json` | Sync metadata: `last_sync`, `mode`, `entries`, `index_fingerprint`, `schema_version`. |
| `history.json` | The append-only `SynchronizationHistory`. |

Layout: `<base>/<repository>/{index,meta,history}.json`. The base path defaults to
`storage/app/page-builder/design-library/sync-cache` (config:
`page-builder.design_library.sync_cache_path`).

## 2. Offline usage (Rule 9)

```php
$svc = app(CommunitySynchronizationService::class);

$index = $svc->cachedIndex('community'); // reads local files, no network
$svc->history('community');
```

A freshly-constructed service pointed at an existing cache directory reads it directly;
no remote is contacted.

## 3. Write safety

Writes are atomic and **Windows-safe**: a temp file is written then renamed, with a
direct-write fallback where `rename()` will not overwrite (mirroring
`CommunityResourceStore`). `clearstatcache()` is called after each write.

## 4. Provider integration (Rule 11)

`CommunityRepositoryProvider::synchronizedIndex()` reads this cache to become **aware**
of remotely-synchronized resources. It still serves for insertion only the local,
payload-bearing resources from its store — synchronization transports no payload, so
the ONE repository, registry and library are unchanged.
