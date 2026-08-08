<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Community\Synchronization;

/**
 * The immutable, append-only synchronization history of a repository (Phase 26.50C,
 * Rule 10).
 *
 * Each run records ONE entry: sync time, repository, the per-class change counts
 * (added / updated / deprecated / archived / removed / unchanged), the number of
 * reported conflicts, the sync mode and the run duration. This is HISTORY, not an
 * audit log (Rule 10) — it never records who, why or any payload. {@see record()}
 * returns a NEW history; entries are never mutated.
 *
 * @see docs/synchronization-history.md
 */
final class SynchronizationHistory
{
    /**
     * @param  array<int, array<string, mixed>>  $entries
     */
    public function __construct(
        private readonly array $entries = [],
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $entries
     */
    public static function fromArray(array $entries): self
    {
        $clean = [];
        foreach ($entries as $entry) {
            if (is_array($entry)) {
                $clean[] = $entry;
            }
        }

        return new self($clean);
    }

    /**
     * Append a run, returning a NEW history (the receiver is unchanged).
     */
    public function record(SynchronizationResult $result, int $syncTime, int $durationMs, string $mode): self
    {
        $counts = $result->counts();

        $entry = [
            'sync_time' => $syncTime,
            'repository' => $result->repository,
            'mode' => $mode,
            'added' => $counts['added'],
            'updated' => $counts['updated'],
            'deprecated' => $counts['deprecated'],
            'archived' => $counts['archived'],
            'removed' => $counts['removed'],
            'unchanged' => $counts['unchanged'],
            'conflicts' => $counts['conflicts'],
            'duration_ms' => $durationMs,
        ];

        return new self([...$this->entries, $entry]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    public function isEmpty(): bool
    {
        return $this->entries === [];
    }

    public function count(): int
    {
        return count($this->entries);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function latest(): ?array
    {
        return $this->entries === [] ? null : $this->entries[count($this->entries) - 1];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function toArray(): array
    {
        return $this->entries;
    }
}
