<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Editor\Translation;

/**
 * Outcome of ensuring a locale's layout exists on first open (LT-M1B).
 *
 * Immutable. Reports whether this request cloned a new translated layout, whether
 * an initial baseline was captured, and the resolved layout id — all read-only
 * diagnostics for the editor boot payload.
 */
final class LayoutProvisionResult
{
    private function __construct(
        public readonly bool $cloned,
        public readonly bool $baselineCaptured,
        public readonly int|string|null $layoutId,
    ) {}

    public static function sourceLocale(): self
    {
        return new self(false, false, null);
    }

    public static function alreadyProvisioned(): self
    {
        return new self(false, false, null);
    }

    public static function cloned(int|string|null $layoutId, bool $baselineCaptured): self
    {
        return new self(true, $baselineCaptured, $layoutId);
    }
}
