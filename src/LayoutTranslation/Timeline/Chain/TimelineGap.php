<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Timeline\Chain;

use Plugins\PageBuilder\LayoutTranslation\Timeline\Entry\TimelineEntryId;

/**
 * A break in baseline continuity between two adjacent entries (LT-9B, per INV-LT-9-TL-06).
 *
 * Layer: Translation (timeline / value). Produced when `entry(N).baseline_after !=
 * entry(N+1).baseline_before`. It is surfaced explicitly — never silently ignored or bridged. Immutable and
 * payload-safe (fingerprints + ids only).
 */
final class TimelineGap
{
    public function __construct(
        public readonly TimelineEntryId $afterEntryId,
        public readonly TimelineEntryId $beforeEntryId,
        public readonly string $expectedBaseline,
        public readonly string $actualBaseline,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'after_entry_id' => $this->afterEntryId->value,
            'before_entry_id' => $this->beforeEntryId->value,
            'expected_baseline' => $this->expectedBaseline,
            'actual_baseline' => $this->actualBaseline,
        ];
    }
}
