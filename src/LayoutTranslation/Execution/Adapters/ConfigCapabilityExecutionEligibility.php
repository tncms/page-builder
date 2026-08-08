<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Execution\Adapters;

use Plugins\PageBuilder\LayoutTranslation\Contracts\LayoutTranslationCapabilityResolverInterface;
use Plugins\PageBuilder\LayoutTranslation\Execution\Contracts\ExecutionEligibilityInterface;
use Plugins\PageBuilder\LayoutTranslation\Identity\PageTranslationKey;
use Plugins\PageBuilder\LayoutTranslation\LayoutTranslationConfig;

/**
 * Production eligibility gate (LT-7C): feature flag + implementation capability.
 *
 * Layer: Translation (execution adapter). Bridges the coordinator's
 * {@see ExecutionEligibilityInterface} to the frozen `LayoutTranslationConfig` feature flag and the
 * implementation-static capability resolver. Read-only; it performs no write and resolves no per-target
 * state (capability is status-free per LT-1.5).
 */
final class ConfigCapabilityExecutionEligibility implements ExecutionEligibilityInterface
{
    public function __construct(
        private readonly LayoutTranslationConfig $config,
        private readonly LayoutTranslationCapabilityResolverInterface $capabilities,
    ) {}

    public function featureEnabled(): bool
    {
        return $this->config->enabled();
    }

    public function supportsSync(PageTranslationKey $target): bool
    {
        return $this->capabilities->capabilitiesFor()->supportsSync();
    }
}
