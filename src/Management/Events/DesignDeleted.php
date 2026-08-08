<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Management\Events;

/**
 * Dispatched after a "My Designs" resource is soft-deleted (Phase 26.49,
 * Rule 18). Delete is soft only — the resource is never permanently removed and
 * a future cleanup job may purge it. This phase ships NO listeners.
 */
final class DesignDeleted
{
    public function __construct(
        public readonly string $rid,
        public readonly ?string $actor = null,
    ) {
    }
}
