<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Management\Events;

/**
 * Dispatched after a "My Designs" resource is duplicated (Phase 26.49, Rule 18).
 * The duplicate is a distinct identity (new RID) that shares the original's
 * immutable payload and fingerprint. This phase ships NO listeners.
 */
final class DesignDuplicated
{
    public function __construct(
        public readonly string $sourceRid,
        public readonly string $rid,
        public readonly ?string $actor = null,
    ) {
    }
}
