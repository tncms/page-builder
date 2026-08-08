<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Timeline\Model;

use Plugins\PageBuilder\LayoutTranslation\Timeline\Chain\TimelineChain;
use Plugins\PageBuilder\LayoutTranslation\Timeline\Entry\TimelineEntry;
use Plugins\PageBuilder\LayoutTranslation\Timeline\Entry\TimelineEntryId;

/**
 * The immutable, read-only synchronization timeline for a target (LT-9B, per LT-9A §2).
 *
 * Layer: Translation (timeline / model). A deterministic read projection of append-only history: ordered
 * entries plus the baseline-continuity chain. It never mutates history, snapshots, or coordinator state
 * (INV-LT-9B-RT-01). Derived views (summary, statistics, projections) are computed on demand and never
 * persisted.
 */
final class SynchronizationTimeline
{
    /**
     * @param  array<int, TimelineEntry>  $entries  ordered by (executed_at, id)
     */
    private function __construct(
        private readonly string $pageTranslationKey,
        private readonly array $entries,
        private readonly TimelineChain $chain,
    ) {}

    /**
     * @param  array<int, TimelineEntry>  $entries
     */
    public static function of(string $pageTranslationKey, array $entries, TimelineChain $chain): self
    {
        return new self($pageTranslationKey, array_values($entries), $chain);
    }

    public function pageTranslationKey(): string
    {
        return $this->pageTranslationKey;
    }

    /**
     * @return array<int, TimelineEntry>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    public function chain(): TimelineChain
    {
        return $this->chain;
    }

    public function isContinuous(): bool
    {
        return $this->chain->isContinuous();
    }

    public function entryFor(TimelineEntryId $id): ?TimelineEntry
    {
        foreach ($this->entries as $entry) {
            if ($entry->id->equals($id)) {
                return $entry;
            }
        }

        return null;
    }

    public function count(): int
    {
        return count($this->entries);
    }

    public function isEmpty(): bool
    {
        return $this->entries === [];
    }

    public function summary(): TimelineSummary
    {
        $first = $this->entries[0] ?? null;
        $last = $this->entries !== [] ? $this->entries[count($this->entries) - 1] : null;

        return new TimelineSummary(
            $this->pageTranslationKey,
            count($this->entries),
            $this->chain->gapCount(),
            $first?->executedAt,
            $last?->executedAt,
            $last?->baselineAfter,
            $this->chain->isContinuous(),
        );
    }
}
