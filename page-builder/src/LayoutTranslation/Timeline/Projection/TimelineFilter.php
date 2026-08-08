<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Timeline\Projection;

use Plugins\PageBuilder\LayoutTranslation\Timeline\Entry\TimelineEntry;

/**
 * An immutable entry-local filter for timeline projections (LT-9B, per INV-LT-9B-RT-03).
 *
 * Layer: Translation (timeline / projection). Criteria compose immutably — each `with…` returns a NEW
 * filter. Applying it (via {@see TimelineProjectionBuilder}) yields a NEW projection; the filter mutates
 * nothing.
 */
final class TimelineFilter
{
    private function __construct(
        private readonly ?string $actor,
        private readonly ?string $executedFrom,
        private readonly ?string $executedTo,
        private readonly int $minAppliedCount,
    ) {}

    public static function any(): self
    {
        return new self(null, null, null, 0);
    }

    public function withActor(?string $actor): self
    {
        return new self($actor, $this->executedFrom, $this->executedTo, $this->minAppliedCount);
    }

    public function withExecutedFrom(?string $executedFrom): self
    {
        return new self($this->actor, $executedFrom, $this->executedTo, $this->minAppliedCount);
    }

    public function withExecutedTo(?string $executedTo): self
    {
        return new self($this->actor, $this->executedFrom, $executedTo, $this->minAppliedCount);
    }

    public function withMinAppliedCount(int $minAppliedCount): self
    {
        return new self($this->actor, $this->executedFrom, $this->executedTo, max(0, $minAppliedCount));
    }

    public function matches(TimelineEntry $entry): bool
    {
        if ($this->actor !== null && $entry->actor !== $this->actor) {
            return false;
        }
        if ($this->executedFrom !== null && strcmp($entry->executedAt, $this->executedFrom) < 0) {
            return false;
        }
        if ($this->executedTo !== null && strcmp($entry->executedAt, $this->executedTo) > 0) {
            return false;
        }
        if ($entry->appliedCount < $this->minAppliedCount) {
            return false;
        }

        return true;
    }
}
