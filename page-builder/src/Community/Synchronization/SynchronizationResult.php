<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Community\Synchronization;

/**
 * The deterministic result of one synchronization run (Phase 26.50C, Rules 4 & 8).
 *
 * The six canonical change classes — {@see $added}, {@see $updated},
 * {@see $deprecated}, {@see $archived}, {@see $removed}, {@see $unchanged} — describe
 * exactly how the remote index differs from the local cache (Rule 4). Two safety
 * reports accompany them: {@see $conflicts} records immutable-version divergence
 * (same rid + version, different fingerprint) which synchronization REPORTS but never
 * merges (Rule 8), and {@see $rejected} records metadata refused because it failed
 * manifest / schema / compatibility validation before acceptance (Rule 13).
 *
 * Synchronization installs nothing and transfers no payload — this result is a report
 * of metadata differences only (Rule 16).
 *
 * @see docs/synchronization-protocol.md
 * @see docs/conflict-policy.md
 */
final class SynchronizationResult
{
    /**
     * @param  array<int, RepositoryIndexEntry>  $added
     * @param  array<int, RepositoryIndexEntry>  $updated
     * @param  array<int, RepositoryIndexEntry>  $deprecated
     * @param  array<int, RepositoryIndexEntry>  $archived
     * @param  array<int, RepositoryIndexEntry>  $removed
     * @param  array<int, RepositoryIndexEntry>  $unchanged
     * @param  array<int, array{rid: string, version: string, cached_fingerprint: string, remote_fingerprint: string}>  $conflicts
     * @param  array<int, array{rid: string, reason: string}>  $rejected
     */
    public function __construct(
        public readonly string $repository,
        public readonly array $added = [],
        public readonly array $updated = [],
        public readonly array $deprecated = [],
        public readonly array $archived = [],
        public readonly array $removed = [],
        public readonly array $unchanged = [],
        public readonly array $conflicts = [],
        public readonly array $rejected = [],
    ) {}

    public static function empty(string $repository): self
    {
        return new self($repository);
    }

    /**
     * Whether the remote state differs from the cache in any of the six canonical
     * change classes (Rule 4). Conflicts and rejections are safety reports, not
     * changes, and are excluded here.
     */
    public function hasChanges(): bool
    {
        return $this->added !== []
            || $this->updated !== []
            || $this->deprecated !== []
            || $this->archived !== []
            || $this->removed !== [];
    }

    public function hasConflicts(): bool
    {
        return $this->conflicts !== [];
    }

    /**
     * @return array<string, int>
     */
    public function counts(): array
    {
        return [
            'added' => count($this->added),
            'updated' => count($this->updated),
            'deprecated' => count($this->deprecated),
            'archived' => count($this->archived),
            'removed' => count($this->removed),
            'unchanged' => count($this->unchanged),
            'conflicts' => count($this->conflicts),
            'rejected' => count($this->rejected),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $rids = static fn (array $entries): array => array_map(static fn (RepositoryIndexEntry $e): string => $e->rid, $entries);

        return [
            'repository' => $this->repository,
            'added' => $rids($this->added),
            'updated' => $rids($this->updated),
            'deprecated' => $rids($this->deprecated),
            'archived' => $rids($this->archived),
            'removed' => $rids($this->removed),
            'unchanged' => $rids($this->unchanged),
            'conflicts' => $this->conflicts,
            'rejected' => $this->rejected,
        ];
    }

    public function summary(): string
    {
        $c = $this->counts();

        return sprintf(
            'Sync %s — +%d ~%d deprecated:%d archived:%d -%d =%d (conflicts:%d rejected:%d)',
            $this->repository,
            $c['added'], $c['updated'], $c['deprecated'], $c['archived'], $c['removed'], $c['unchanged'], $c['conflicts'], $c['rejected'],
        );
    }
}
