<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Review\Contracts;

use Plugins\PageBuilder\LayoutTranslation\Review\Session\ReviewSessionId;
use Plugins\PageBuilder\LayoutTranslation\Review\Session\ReviewSessionSnapshot;

/**
 * Holds a paused session's payload-safe snapshot for resume (LT-8B, per
 * LT-8A-REVIEW-WORKSPACE-ARCHITECTURE §8.3 and INV-LT-8A-06).
 *
 * Layer: Translation (review boundary). The store is ADVISORY, not authoritative: a snapshot can go stale
 * and is re-verified on resume; the coordinator re-verifies again at submit (INV-LT-8A-04). The default
 * implementation is EPHEMERAL and payload-safe; LT-8A introduces no durable backing and no migration
 * (INV-LT-8A-06). A snapshot is enough to reconstruct a session, never enough to reconstruct content.
 */
interface ReviewSessionStoreInterface
{
    public function put(ReviewSessionSnapshot $snapshot): void;

    public function get(ReviewSessionId $id): ?ReviewSessionSnapshot;

    public function forget(ReviewSessionId $id): void;
}
