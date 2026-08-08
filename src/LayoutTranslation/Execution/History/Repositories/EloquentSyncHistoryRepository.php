<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Execution\History\Repositories;

use Plugins\PageBuilder\LayoutTranslation\Execution\Contracts\SyncHistoryRecorderInterface;
use Plugins\PageBuilder\LayoutTranslation\Execution\History\Models\LayoutTranslationSyncHistory;
use Plugins\PageBuilder\LayoutTranslation\Execution\History\SyncHistoryRecord;

/**
 * Eloquent-backed, append-only synchronization history repository (LT-7C, per INV-LT-7-EXEC-02).
 *
 * Layer: Translation (execution history / repository). Its only write is an INSERT — there is no update
 * or delete path. It participates in the coordinator's transaction (a row exists iff the sync committed)
 * and returns the new history id. The Eloquent model never crosses this boundary.
 */
final class EloquentSyncHistoryRepository implements SyncHistoryRecorderInterface
{
    public function record(SyncHistoryRecord $record): int
    {
        $model = LayoutTranslationSyncHistory::query()->create([
            'page_translation_key' => $record->pageTranslationKey,
            'actor' => $record->actor,
            'plan_fingerprint' => $record->planFingerprint,
            'baseline_before' => $record->baselineBefore,
            'baseline_after' => $record->baselineAfter,
            'source_layout_id' => $record->sourceLayoutId,
            'applied_count' => $record->appliedCount,
            'skipped_count' => $record->skippedCount,
            'applied_items' => $record->appliedItems,
            'skipped_items' => $record->skippedItems,
            'executed_at' => date('c'),
        ]);

        return (int) $model->getKey();
    }
}
