<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Management\Events;

/**
 * Dispatched after a "My Designs" resource is archived (Phase 26.49, Rule 18).
 * The resource is hidden from normal browsing but remains recoverable. This
 * phase ships NO listeners.
 */
final class DesignArchived
{
    public function __construct(
        public readonly string $rid,
        public readonly ?string $actor = null,
    ) {
    }
}
