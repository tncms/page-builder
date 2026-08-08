<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Timeline\Model;

use Plugins\PageBuilder\LayoutTranslation\Timeline\Replay\ReplayResult;

/**
 * Immutable, derived statistics over a timeline and (optionally) its replay results (LT-9B).
 *
 * Layer: Translation (timeline / value). Purely DERIVED and never persisted (INV-LT-9B-RT-03): entry and
 * gap counts come from the timeline; success / failure / divergence counts come from replay verification
 * (`Reproduced` / `Unavailable` / `Diverged`). Recomputed on demand from immutable inputs.
 */
final class TimelineStatistics
{
    public function __construct(
        public readonly int $entryCount,
        public readonly int $gapCount,
        public readonly int $successCount,
        public readonly int $failureCount,
        public readonly int $divergenceCount,
    ) {}

    /**
     * @param  array<int, ReplayResult>  $replayResults
     */
    public static function from(SynchronizationTimeline $timeline, array $replayResults = []): self
    {
        $success = 0;
        $failure = 0;
        $divergence = 0;

        foreach ($replayResults as $result) {
            if ($result->isReproduced()) {
                $success++;
            } elseif ($result->isDiverged()) {
                $divergence++;
            } else {
                $failure++;
            }
        }

        return new self(
            $timeline->count(),
            $timeline->chain()->gapCount(),
            $success,
            $failure,
            $divergence,
        );
    }

    /**
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return [
            'entry_count' => $this->entryCount,
            'gap_count' => $this->gapCount,
            'success_count' => $this->successCount,
            'failure_count' => $this->failureCount,
            'divergence_count' => $this->divergenceCount,
        ];
    }
}
