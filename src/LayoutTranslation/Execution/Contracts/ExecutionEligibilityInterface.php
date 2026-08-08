<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Execution\Contracts;

use Plugins\PageBuilder\LayoutTranslation\Identity\PageTranslationKey;

/**
 * The cheap, read-only eligibility gate for a synchronization (LT-7B3).
 *
 * Layer: Translation (execution eligibility). Bridges the feature flag
 * (`page-builder.translation.enabled`) and the LT-1 capability resolver (`supportsSync`). Evaluated
 * before the transaction; it performs no write.
 */
interface ExecutionEligibilityInterface
{
    public function featureEnabled(): bool;

    public function supportsSync(PageTranslationKey $target): bool;
}
