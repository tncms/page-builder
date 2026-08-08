<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Timeline\Builder;

use Plugins\PageBuilder\LayoutTranslation\Identity\PageTranslationKey;
use Plugins\PageBuilder\LayoutTranslation\Timeline\Chain\TimelineChain;
use Plugins\PageBuilder\LayoutTranslation\Timeline\Chain\TimelineGap;
use Plugins\PageBuilder\LayoutTranslation\Timeline\Contracts\SynchronizationHistoryReaderInterface;
use Plugins\PageBuilder\LayoutTranslation\Timeline\Entry\TimelineEntry;
use Plugins\PageBuilder\LayoutTranslation\Timeline\History\TimelineHistoryRecord;
use Plugins\PageBuilder\LayoutTranslation\Timeline\Model\SynchronizationTimeline;

/**
 * Builds an immutable {@see SynchronizationTimeline} from history (LT-9B, per LT-9A §2 and INV-LT-9-TL-06).
 *
 * Layer: Translation (timeline / builder). Deterministic and read-only: it reconstructs entries in history
 * order, then verifies baseline continuity — for each adjacent pair it asserts `entry(N).baseline_after ==
 * entry(N+1).baseline_before`, producing a {@see TimelineGap} on any mismatch (never silently ignored). It
 * mutates nothing (INV-LT-9B-RT-01).
 */
final class TimelineBuilder
{
    public function __construct(
        private readonly ?SynchronizationHistoryReaderInterface $history = null,
    ) {}

    public function buildForTarget(PageTranslationKey $target): SynchronizationTimeline
    {
        if ($this->history === null) {
            return SynchronizationTimeline::of($target->value(), [], TimelineChain::of([]));
        }

        return $this->build($target->value(), $this->history->forTarget($target));
    }

    /**
     * Build a timeline from an ordered list of history records.
     *
     * @param  array<int, TimelineHistoryRecord>  $records  ordered by (executed_at, id)
     */
    public function build(string $pageTranslationKey, array $records): SynchronizationTimeline
    {
        $entries = array_map(
            static fn (TimelineHistoryRecord $r): TimelineEntry => TimelineEntry::fromHistory($r),
            array_values($records),
        );

        $chain = TimelineChain::of($this->detectGaps($entries));

        return SynchronizationTimeline::of($pageTranslationKey, $entries, $chain);
    }

    /**
     * @param  array<int, TimelineEntry>  $entries
     * @return array<int, TimelineGap>
     */
    private function detectGaps(array $entries): array
    {
        $gaps = [];

        for ($i = 0, $n = count($entries) - 1; $i < $n; $i++) {
            $current = $entries[$i];
            $next = $entries[$i + 1];

            if (! hash_equals($current->baselineAfter, $next->baselineBefore)) {
                $gaps[] = new TimelineGap(
                    $current->id,
                    $next->id,
                    $current->baselineAfter,
                    $next->baselineBefore,
                );
            }
        }

        return $gaps;
    }
}
