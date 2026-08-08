<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Execution\History;

/**
 * One append-only synchronization history record (LT-7B3, per LT-7-FINAL-GOVERNANCE INV-LT-7-EXEC-02).
 *
 * Layer: Translation (execution history / value). A DECISION reconstruction record — not a log. It
 * preserves the plan fingerprint, the baseline before and after, and every applied and skipped item with
 * its reviewer decision, so an execution can be reconstructed and reversed WITHOUT any payload snapshot.
 * Payload-safe: origins, enums, fingerprints, and counts only — never node content.
 */
final class SyncHistoryRecord
{
    /**
     * @param  array<int, array{origin:string, source_change:string, decision:string}>  $appliedItems
     * @param  array<int, array{origin:string, source_change:string, decision:string}>  $skippedItems
     */
    public function __construct(
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
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
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
        ];
    }
}
