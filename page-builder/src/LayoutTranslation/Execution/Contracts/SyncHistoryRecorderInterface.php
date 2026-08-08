<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Execution\Contracts;

use Plugins\PageBuilder\LayoutTranslation\Execution\History\SyncHistoryRecord;

/**
 * Appends one synchronization history record (LT-7B3, per INV-LT-7-EXEC-02).
 *
 * Layer: Translation (execution history). Append-only — it only ever inserts, never updates or deletes.
 * It participates in the coordinator's transaction (a row exists iff the sync committed) and returns the
 * new history id.
 */
interface SyncHistoryRecorderInterface
{
    public function record(SyncHistoryRecord $record): int;
}
