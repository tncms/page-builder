<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\TimelineInfrastructure;

use Plugins\PageBuilder\LayoutTranslation\Identity\PageTranslationKey;
use Plugins\PageBuilder\LayoutTranslation\Timeline\Builder\TimelineBuilder;
use Plugins\PageBuilder\LayoutTranslation\Timeline\Entry\TimelineEntryId;
use Plugins\PageBuilder\LayoutTranslation\Timeline\Explainability\ExplainabilityReport;
use Plugins\PageBuilder\LayoutTranslation\Timeline\Explainability\ExplainabilityRuntime;
use Plugins\PageBuilder\LayoutTranslation\Timeline\Model\SynchronizationTimeline;
use Plugins\PageBuilder\LayoutTranslation\Timeline\Model\TimelineStatistics;
use Plugins\PageBuilder\LayoutTranslation\Timeline\Replay\ReplayResult;
use Plugins\PageBuilder\LayoutTranslation\Timeline\Replay\ReplayRuntime;

/**
 * The read-only application facade over the frozen Timeline Runtime (LT-9C, per INV-LT-9C-01).
 *
 * Layer: Translation (timeline application). It CONSTRUCTS the timeline through the runtime builder (fed by
 * the Eloquent history reader) and delegates replay and explainability to the runtime — it implements NONE
 * of that domain behavior itself. Every timeline, projection, replay report, and explanation the
 * presentation shows is produced here, through the runtime, never around it. It never mutates state.
 */
final class TimelineApplicationService
{
    public function __construct(
        private readonly TimelineBuilder $builder,
        private readonly ReplayRuntime $replay,
        private readonly ExplainabilityRuntime $explain,
    ) {}

    public function timeline(PageTranslationKey $target): SynchronizationTimeline
    {
        return $this->builder->buildForTarget($target);
    }

    /**
     * Replay one entry through the Replay Runtime (observational only — INV-LT-9C-03).
     */
    public function replay(PageTranslationKey $target, TimelineEntryId $entryId): ReplayResult
    {
        $entry = $this->timeline($target)->entryFor($entryId);
        if ($entry === null) {
            return ReplayResult::unavailable($entryId, 'entry_not_found');
        }

        return $this->replay->replay($entry);
    }

    public function explain(PageTranslationKey $target, TimelineEntryId $entryId): ?ExplainabilityReport
    {
        $entry = $this->timeline($target)->entryFor($entryId);

        return $entry === null ? null : $this->explain->explain($entry);
    }

    /**
     * @param  array<int, ReplayResult>  $replayResults
     */
    public function statistics(PageTranslationKey $target, array $replayResults = []): TimelineStatistics
    {
        return TimelineStatistics::from($this->timeline($target), $replayResults);
    }
}
