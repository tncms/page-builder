<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Timeline\Entry;

use Plugins\PageBuilder\LayoutTranslation\Timeline\History\TimelineHistoryRecord;

/**
 * One immutable timeline entry — the read projection of a committed sync (LT-9B, per LT-9A §2).
 *
 * Layer: Translation (timeline / value). Payload-safe metadata straight from history, plus the snapshot
 * **references** (`baselineBefore` / `baselineAfter`) consumers resolve to documents through the
 * {@see \Plugins\PageBuilder\LayoutTranslation\Timeline\Resolution\TimelineDocumentResolver}. It carries no
 * content and is never mutated (INV-LT-9B-RT-01/03).
 */
final class TimelineEntry
{
    /**
     * @param  array<int, array{origin:string, source_change:string, decision:string}>  $appliedItems
     * @param  array<int, array{origin:string, source_change:string, decision:string}>  $skippedItems
     */
    public function __construct(
        public readonly TimelineEntryId $id,
        public readonly string $pageTranslationKey,
        public readonly ?string $actor,
        public readonly string $planFingerprint,
        public readonly string $baselineBefore,
        public readonly string $baselineAfter,
        public readonly int $sourceLayoutId,
        public readonly int $appliedCount,
        public readonly int $skippedCount,
        public readonly array $appliedItems,
        public readonly array $skippedItems,
        public readonly string $executedAt,
    ) {}

    public static function fromHistory(TimelineHistoryRecord $record): self
    {
        return new self(
            TimelineEntryId::of($record->id),
            $record->pageTranslationKey,
            $record->actor,
            $record->planFingerprint,
            $record->baselineBefore,
            $record->baselineAfter,
            $record->sourceLayoutId,
            $record->appliedCount,
            $record->skippedCount,
            $record->appliedItems,
            $record->skippedItems,
            $record->executedAt,
        );
    }

    /** The baseline the sync advanced FROM (a snapshot reference). */
    public function baselineBeforeReference(): string
    {
        return $this->baselineBefore;
    }

    /** The baseline the sync advanced TO — content-addresses the source document (a snapshot reference). */
    public function baselineAfterReference(): string
    {
        return $this->baselineAfter;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id->value,
            'page_translation_key' => $this->pageTranslationKey,
            'actor' => $this->actor,
            'plan_fingerprint' => $this->planFingerprint,
            'baseline_before' => $this->baselineBefore,
            'baseline_after' => $this->baselineAfter,
            'source_layout_id' => $this->sourceLayoutId,
            'applied_count' => $this->appliedCount,
            'skipped_count' => $this->skippedCount,
            'applied_items' => $this->appliedItems,
            'skipped_items' => $this->skippedItems,
            'executed_at' => $this->executedAt,
        ];
    }
}
