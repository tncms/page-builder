<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Capture\Events;

/**
 * Dispatched once after a design is successfully captured (Phase 26.48, Rule 19).
 *
 * The single capture event. Future consumers (sync, publish, marketplace,
 * analytics) may listen for it — this phase ships NO listeners. It carries only
 * the public handle + fingerprint, never Builder runtime state.
 */
final class DesignResourceCaptured
{
    public function __construct(
        public readonly string $rid,
        public readonly string $fingerprint,
        public readonly string $kind,
        public readonly ?string $author = null,
    ) {
    }
}
