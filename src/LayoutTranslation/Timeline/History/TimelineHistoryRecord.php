<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Timeline\History;

/**
 * One immutable, framework-free read view of an append-only synchronization history row (LT-9B).
 *
 * Layer: Translation (timeline / read DTO). The Timeline runtime consumes history through this DTO — never
 * an Eloquent model or row. It mirrors the frozen LT-7 history shape (payload-safe: fingerprints, origins,
 * enums, counts — no content) and is read-only: the runtime never writes it (INV-LT-9B-RT-01).
 */
final class TimelineHistoryRecord
{
    /**
     * @param  array<int, array{origin:string, source_change:string, decision:string}>  $appliedItems
     * @param  array<int, array{origin:string, source_change:string, decision:string}>  $skippedItems
     */
    public function __construct(
        public readonly int $id,
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

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            (int) ($row['id'] ?? 0),
            (string) ($row['page_translation_key'] ?? ''),
            isset($row['actor']) ? (string) $row['actor'] : null,
            (string) ($row['plan_fingerprint'] ?? ''),
            (string) ($row['baseline_before'] ?? ''),
            (string) ($row['baseline_after'] ?? ''),
            (int) ($row['source_layout_id'] ?? 0),
            (int) ($row['applied_count'] ?? 0),
            (int) ($row['skipped_count'] ?? 0),
            is_array($row['applied_items'] ?? null) ? $row['applied_items'] : [],
            is_array($row['skipped_items'] ?? null) ? $row['skipped_items'] : [],
            (string) ($row['executed_at'] ?? ''),
        );
    }
}
