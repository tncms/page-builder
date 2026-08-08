<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Community\Synchronization;

use Plugins\PageBuilder\Community\Publishing\ResourceStatus;
use Plugins\PageBuilder\Community\Synchronization\Contracts\RepositoryIndexSource;

/**
 * The official Community synchronization service (Phase 26.50C, Rules 3–8).
 *
 * It performs the whole synchronization protocol and NOTHING ELSE: fetch a remote
 * {@see RepositoryIndex}, compare it against the local {@see SynchronizationCache},
 * detect changes (added / updated / deprecated / archived / removed / unchanged),
 * report conflicts and rejected metadata, refresh the cache and append an entry to the
 * {@see SynchronizationHistory} (Rules 3, 4, 10). It never downloads payload, installs,
 * resolves dependencies or touches commerce (Rule 16).
 *
 * Synchronization is READ-ONLY and pull-only: the local side never overwrites the
 * remote, and an immutable-version divergence is REPORTED as a conflict, never merged
 * (Rule 8). The identical protocol is reused by Marketplace, Enterprise and PRO
 * providers; only the transport ({@see RepositoryIndexSource}) and authorization differ
 * (Rule 15).
 *
 * @see docs/synchronization-protocol.md
 */
final class CommunitySynchronizationService
{
    public function __construct(
        private readonly SynchronizationCache $cache,
    ) {}

    /**
     * Fetch the remote index through a transport-neutral source, then synchronize
     * (Rule 3). No transport is assumed — the source may be a file, mirror, portable
     * package or HTTP endpoint (Rule 12).
     */
    public function sync(string $repository, RepositoryIndexSource $source, ?int $now = null): SynchronizationResult
    {
        return $this->synchronize($repository, $source->fetch($repository), $now);
    }

    /**
     * Synchronize an already-fetched remote index into the local cache: compare,
     * refresh the cache and record history. Returns the deterministic result.
     */
    public function synchronize(string $repository, RepositoryIndex $remote, ?int $now = null): SynchronizationResult
    {
        $now ??= time();
        $start = hrtime(true);

        $cached = $this->cache->read($repository);
        $result = $this->compare($repository, $remote, $cached);
        $next = $this->merge($repository, $remote, $cached, $result, $now);

        $this->cache->write($repository, $next);
        $this->cache->writeMeta($repository, [
            'repository' => $repository,
            'last_sync' => $now,
            'mode' => $remote->mode,
            'entries' => $next->count(),
            'index_fingerprint' => $next->fingerprint(),
            'schema_version' => RepositoryIndex::SCHEMA,
        ]);

        $durationMs = (int) ((hrtime(true) - $start) / 1_000_000);
        $history = $this->cache->history($repository)->record($result, $now, $durationMs, $remote->mode);
        $this->cache->writeHistory($repository, $history);

        return $result;
    }

    /**
     * Pure, deterministic change detection between a remote index and the cached one
     * (Rules 4 & 5). Never compares payload; classifies solely by rid, fingerprint,
     * version, status and presence. No I/O.
     */
    public function compare(string $repository, RepositoryIndex $remote, ?RepositoryIndex $cached): SynchronizationResult
    {
        $cachedByRid = $cached?->entriesByRid() ?? [];
        $remoteEntries = $remote->entriesByRid();
        $remoteRemoved = $remote->removedByRid();

        $added = $updated = $deprecated = $archived = $removed = $unchanged = $conflicts = $rejected = [];

        foreach ($remoteEntries as $rid => $entry) {
            $reason = $entry->validationError();
            if ($reason !== null) {
                // Rule 13 — refuse metadata that fails manifest / schema / compatibility
                // validation; it is never accepted into the cache.
                $rejected[] = ['rid' => $rid, 'reason' => $reason];

                continue;
            }

            $cachedEntry = $cachedByRid[$rid] ?? null;
            if ($cachedEntry === null) {
                $added[] = $entry;

                continue;
            }

            // Immutable-version divergence: same version, different fingerprint. Report
            // only — synchronization never merges divergent content (Rule 8).
            if ($entry->version === $cachedEntry->version && $entry->fingerprint !== $cachedEntry->fingerprint) {
                $conflicts[] = [
                    'rid' => $rid,
                    'version' => $entry->version,
                    'cached_fingerprint' => $cachedEntry->fingerprint,
                    'remote_fingerprint' => $entry->fingerprint,
                ];

                continue;
            }

            if ($entry->isDeprecated() && ! $cachedEntry->isDeprecated()) {
                $deprecated[] = $entry;

                continue;
            }

            if ($entry->contentDiffersFrom($cachedEntry)) {
                $updated[] = $entry;

                continue;
            }

            $unchanged[] = $entry;
        }

        // Explicit tombstones — archival vs removal is carried by the tombstone status.
        foreach ($remoteRemoved as $tomb) {
            if ($tomb->isArchivedTombstone()) {
                $archived[] = $tomb;
            } else {
                $removed[] = $tomb;
            }
        }

        // A full index is a complete snapshot: a cached rid that is neither present nor
        // tombstoned has been removed upstream (Rule 4). Incremental indexes carry only
        // a delta, so absence there is NOT a removal.
        if ($remote->isFull()) {
            foreach ($cachedByRid as $rid => $cachedEntry) {
                if (! isset($remoteEntries[$rid]) && ! isset($remoteRemoved[$rid])) {
                    $removed[] = $cachedEntry;
                }
            }
        }

        return new SynchronizationResult($repository, $added, $updated, $deprecated, $archived, $removed, $unchanged, $conflicts, $rejected);
    }

    /**
     * The last synchronized index for a repository (offline read, Rule 9).
     */
    public function cachedIndex(string $repository): ?RepositoryIndex
    {
        return $this->cache->read($repository);
    }

    public function history(string $repository): SynchronizationHistory
    {
        return $this->cache->history($repository);
    }

    /**
     * Build the next cached index deterministically from the sync outcome. This is
     * cache maintenance, NOT a content merge (Rule 8): a full index replaces the
     * snapshot, an incremental index upserts onto it, tombstones drop rids, and both
     * conflicting and rejected rids retain their existing cached entry (never
     * overwritten, never accepted).
     */
    private function merge(string $repository, RepositoryIndex $remote, ?RepositoryIndex $cached, SynchronizationResult $result, int $now): RepositoryIndex
    {
        $cachedByRid = $cached?->entriesByRid() ?? [];
        $base = $remote->isFull() ? [] : $cachedByRid;

        $conflictRids = array_flip(array_column($result->conflicts, 'rid'));
        $rejectedRids = array_flip(array_column($result->rejected, 'rid'));

        foreach ($remote->entriesByRid() as $rid => $entry) {
            if (isset($conflictRids[$rid]) || isset($rejectedRids[$rid])) {
                // Retain the existing, trusted cached entry — never overwrite with a
                // conflicting fingerprint or accept unvalidated metadata.
                if (isset($cachedByRid[$rid])) {
                    $base[$rid] = $cachedByRid[$rid];
                }

                continue;
            }

            $base[$rid] = $entry;
        }

        foreach ($remote->removedByRid() as $rid => $_tomb) {
            unset($base[$rid]);
        }

        return RepositoryIndex::full($repository, array_values($base), $now);
    }

    /**
     * The lifecycle statuses that ever appear as active entries in an index. Exposed
     * here so callers can reason about exposure without reaching into the enum.
     *
     * @return array<int, string>
     */
    public static function exposedStatuses(): array
    {
        return [ResourceStatus::Published->value, ResourceStatus::Deprecated->value];
    }
}
