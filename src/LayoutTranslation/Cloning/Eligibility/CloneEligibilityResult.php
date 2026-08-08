<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Cloning\Eligibility;

/**
 * The immutable outcome of clone eligibility evaluation (LT-3).
 *
 * Layer: Translation (eligibility). Either eligible, or ineligible with a typed
 * {@see CloneIneligibilityReason}. Read-only; it never mutates or persists.
 */
final class CloneEligibilityResult
{
    private function __construct(
        private readonly bool $eligible,
        private readonly ?CloneIneligibilityReason $reason,
    ) {}

    public static function eligible(): self
    {
        return new self(true, null);
    }

    public static function ineligible(CloneIneligibilityReason $reason): self
    {
        return new self(false, $reason);
    }

    public function isEligible(): bool
    {
        return $this->eligible;
    }

    public function reason(): ?CloneIneligibilityReason
    {
        return $this->reason;
    }
}
