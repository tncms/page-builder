<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\TimelineInfrastructure;

use Plugins\PageBuilder\LayoutTranslation\Execution\History\Models\LayoutTranslationSyncHistory;
use Plugins\PageBuilder\LayoutTranslation\Identity\PageTranslationKey;
use Plugins\PageBuilder\LayoutTranslation\Timeline\Contracts\SynchronizationHistoryReaderInterface;
use Plugins\PageBuilder\LayoutTranslation\Timeline\History\TimelineHistoryRecord;
use Throwable;

/**
 * Eloquent-backed read adapter over append-only synchronization history (LT-9C, per INV-LT-9C-02).
 *
 * Layer: Translation (timeline infrastructure). Implements the frozen LT-9B read port
 * ({@see SynchronizationHistoryReaderInterface}) by translating `pb_layout_translation_sync_history` rows
 * into immutable {@see TimelineHistoryRecord}s, ordered by `(executed_at, id)`. It translates PERSISTENCE
 * ONLY — no domain behavior, no writes (the table stays append-only, owned by execution). Fail-soft: a
 * read error (e.g. an unmigrated table) yields an empty timeline, never a crash. The Eloquent model never
 * crosses this boundary — the runtime never sees it.
 */
final class EloquentTimelineHistoryReader implements SynchronizationHistoryReaderInterface
{
    /**
     * @return array<int, TimelineHistoryRecord>
     */
    public function forTarget(PageTranslationKey $target): array
    {
        try {
            $rows = LayoutTranslationSyncHistory::query()
                ->where('page_translation_key', $target->value())
                ->orderBy('executed_at')
                ->orderBy('id')
                ->get();
        } catch (Throwable) {
            return []; // fail-soft: no readable history → an empty timeline
        }

        $records = [];
        foreach ($rows as $row) {
            $records[] = new TimelineHistoryRecord(
                (int) $row->id,
                (string) $row->page_translation_key,
                $row->actor !== null ? (string) $row->actor : null,
                (string) $row->plan_fingerprint,
                (string) $row->baseline_before,
                (string) $row->baseline_after,
                (int) $row->source_layout_id,
                (int) $row->applied_count,
                (int) $row->skipped_count,
                is_array($row->applied_items) ? $row->applied_items : [],
                is_array($row->skipped_items) ? $row->skipped_items : [],
                (string) $row->executed_at,
            );
        }

        return $records;
    }
}
