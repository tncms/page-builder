# Conflict Policy (Phase 26.50C)

Synchronization is **read-only** and **pull-only**. The local side never overwrites the
remote, and divergent content is **reported, never merged** (Rule 8).

## 1. What is a conflict

An **immutable-version divergence**: the same `rid` at the same `version` presents a
**different fingerprint** in the remote index than in the cache. Because a published
version is immutable (a content change is always a *new* version — 26.50A/B), identical
version + different fingerprint means the two repositories disagree about immutable
content.

```php
$result->conflicts;   // [{ rid, version, cached_fingerprint, remote_fingerprint }]
$result->hasConflicts();
```

## 2. Policy

- **Report only.** A conflict is recorded in `SynchronizationResult::$conflicts`.
- **No merge.** There is no automatic content merge or three-way resolution.
- **No overwrite.** The cache **retains** its existing entry for a conflicting rid; the
  remote fingerprint is not applied.
- Resolution (choosing a winner, republishing a new version) is a human/editorial
  action outside this transport layer.

## 3. Rejected metadata (Rule 13)

Distinct from conflicts: metadata that fails validation before acceptance.

```php
$result->rejected;    // [{ rid, reason }]
```

An entry is rejected when it has an invalid rid, a non-hex fingerprint, a non-semantic
version, an unsupported schema version, or a missing compatibility declaration.
Rejected entries are **never cached**; in a full sync a previously-valid cached entry
for that rid is retained rather than dropped.

## 4. Removal vs archival

- A **full** index is a complete snapshot: a cached rid neither present nor tombstoned
  is classified **removed**.
- **Archival** is signalled explicitly by a tombstone with status `archived`, so it is
  distinguishable from a plain removal in both full and incremental modes.

## 5. Guarantees

| Guarantee | Where |
| --- | --- |
| Pull-only; local never overwrites remote | `synchronize()` retains cache on conflict |
| Deterministic classification (no payload compared) | `compare()` |
| Cache maintenance is not a content merge | `merge()` (full replaces, incremental upserts, tombstones remove) |
