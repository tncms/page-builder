<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Timeline\Contracts;

use Plugins\PageBuilder\LayoutTranslation\Identity\PageTranslationKey;
use Plugins\PageBuilder\LayoutTranslation\Timeline\History\TimelineHistoryRecord;

/**
 * The read seam over append-only synchronization history (LT-9B).
 *
 * Layer: Translation (timeline / read port). Returns a target's committed history as immutable
 * {@see TimelineHistoryRecord}s, ordered by `(executed_at, id)`. Read-only — there is no write method
 * (INV-LT-9B-RT-01, history stays append-only and owned by execution). The concrete Eloquent-backed reader
 * is infrastructure wiring (a later phase); the runtime depends on this abstraction only.
 *
 * @return array<int, TimelineHistoryRecord>
 */
interface SynchronizationHistoryReaderInterface
{
    /**
     * @return array<int, TimelineHistoryRecord>
     */
    public function forTarget(PageTranslationKey $target): array;
}
