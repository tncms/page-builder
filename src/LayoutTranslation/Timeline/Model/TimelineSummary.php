<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Timeline\Model;

/**
 * An immutable, derived summary of a timeline (LT-9B).
 *
 * Layer: Translation (timeline / value). Purely derived from a {@see SynchronizationTimeline}; never
 * persisted (INV-LT-9B-RT-03). Payload-safe (counts, timestamps, the current baseline fingerprint).
 */
final class TimelineSummary
{
    public function __construct(
        public readonly string $pageTranslationKey,
        public readonly int $entryCount,
        public readonly int $gapCount,
        public readonly ?string $firstExecutedAt,
        public readonly ?string $lastExecutedAt,
        public readonly ?string $currentBaseline,
        public readonly bool $continuous,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'page_translation_key' => $this->pageTranslationKey,
            'entry_count' => $this->entryCount,
            'gap_count' => $this->gapCount,
            'first_executed_at' => $this->firstExecutedAt,
            'last_executed_at' => $this->lastExecutedAt,
            'current_baseline' => $this->currentBaseline,
            'continuous' => $this->continuous,
        ];
    }
}
