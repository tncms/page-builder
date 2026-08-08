<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation;

/**
 * The Layout Translation feature flag (LT-1).
 *
 * A single, read-only switch. Everything in the Layout Translation foundation is
 * dormant while this is false — the default — so a single-language site behaves
 * exactly as before and rollback is config-only (flip the flag). When disabled
 * the resolver still resolves the legacy shared layout for every locale; it
 * simply never treats a secondary locale as owning its own layout.
 */
final class LayoutTranslationConfig
{
    public function __construct(private readonly bool $enabled) {}

    public function enabled(): bool
    {
        return $this->enabled;
    }
}
