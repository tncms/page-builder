<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Management\Events;

/**
 * Dispatched after a "My Designs" resource's metadata is edited (Phase 26.49,
 * Rule 18). Payload, fingerprint and RID are unchanged by definition — only
 * descriptive metadata moved. This phase ships NO listeners.
 *
 * @param  array<int, string>  $fields  the metadata keys that changed
 */
final class DesignMetadataUpdated
{
    /**
     * @param  array<int, string>  $fields
     */
    public function __construct(
        public readonly string $rid,
        public readonly array $fields = [],
        public readonly ?string $actor = null,
    ) {
    }
}
