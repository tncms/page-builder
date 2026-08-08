<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Timeline\Projection;

use Plugins\PageBuilder\LayoutTranslation\Timeline\Entry\TimelineEntry;
use Plugins\PageBuilder\LayoutTranslation\Timeline\Model\SynchronizationTimeline;

/**
 * Builds immutable {@see TimelineProjection}s from a timeline (LT-9B, per INV-LT-9B-RT-03).
 *
 * Layer: Translation (timeline / projection). Read-only over the source timeline. Every operation
 * (`all`/`filter`/`select`/`gapsOnly`) returns a NEW projection — nothing is mutated. The builder holds no
 * mutable state.
 */
final class TimelineProjectionBuilder
{
    public function __construct(
        private readonly SynchronizationTimeline $timeline,
    ) {}

    public function all(): TimelineProjection
    {
        return TimelineProjection::of($this->timeline->pageTranslationKey(), $this->timeline->entries());
    }

    public function filter(TimelineFilter $filter): TimelineProjection
    {
        $entries = array_values(array_filter(
            $this->timeline->entries(),
            static fn (TimelineEntry $e): bool => $filter->matches($e),
        ));

        return TimelineProjection::of($this->timeline->pageTranslationKey(), $entries);
    }

    public function select(TimelineSelection $selection): TimelineProjection
    {
        $entries = array_values(array_filter(
            $this->timeline->entries(),
            static fn (TimelineEntry $e): bool => $selection->contains($e->id),
        ));

        return TimelineProjection::of($this->timeline->pageTranslationKey(), $entries);
    }

    /**
     * The entries adjacent to a baseline-continuity gap (either side of each break).
     */
    public function gapsOnly(): TimelineProjection
    {
        $ids = [];
        foreach ($this->timeline->chain()->gaps() as $gap) {
            $ids[$gap->afterEntryId->value] = true;
            $ids[$gap->beforeEntryId->value] = true;
        }

        $entries = array_values(array_filter(
            $this->timeline->entries(),
            static fn (TimelineEntry $e): bool => isset($ids[$e->id->value]),
        ));

        return TimelineProjection::of($this->timeline->pageTranslationKey(), $entries);
    }
}
