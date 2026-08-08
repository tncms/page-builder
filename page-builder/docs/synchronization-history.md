# Synchronization History (Phase 26.50C)

`SynchronizationHistory` is the **immutable, append-only** record of synchronization
runs for a repository. It is **history, not an audit log** (Rule 10): it never records
who, why, or any payload — only what changed and how long it took.

## 1. Entry shape

Each run appends one entry:

| Field | Meaning |
| --- | --- |
| `sync_time` | When the run happened. |
| `repository` | Repository synchronized. |
| `mode` | `full` or `incremental`. |
| `added` / `updated` / `deprecated` / `archived` / `removed` / `unchanged` | Per-class change counts. |
| `conflicts` | Count of reported conflicts. |
| `duration_ms` | Run duration in milliseconds. |

## 2. Append-only

```php
$history = $history->record($result, $syncTime, $durationMs, $mode); // returns a NEW history
```

There is no rollback and no branching; entries are never mutated. History is persisted
by the `SynchronizationCache` at `history.json`.

## 3. Reading

```php
$history = app(CommunitySynchronizationService::class)->history('community');
$history->count();
$history->latest();   // most recent run
$history->entries();  // all runs, oldest → newest
```

The `CommunitySynchronizationService` records one entry automatically at the end of
every `synchronize()` / `sync()` call.
