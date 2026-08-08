<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Management\Events;

/**
 * Dispatched after an archived "My Designs" resource is restored (Phase 26.49,
 * Rule 18). Restore reverses Archive and nothing else. This phase ships NO
 * listeners.
 */
final class DesignRestored
{
    public function __construct(
        public readonly string $rid,
        public readonly ?string $actor = null,
    ) {
    }
}
