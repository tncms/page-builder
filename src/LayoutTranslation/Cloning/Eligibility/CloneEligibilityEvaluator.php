<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Cloning\Eligibility;

use Plugins\PageBuilder\LayoutTranslation\Cloning\Commands\CloneLayoutTranslationCommand;
use Plugins\PageBuilder\LayoutTranslation\Cloning\Contracts\SourceLayoutDocumentReaderInterface;
use Plugins\PageBuilder\LayoutTranslation\Cloning\Contracts\TargetOwnerResolverInterface;
use Plugins\PageBuilder\LayoutTranslation\Contracts\LayoutTranslationCapabilityResolverInterface;
use Plugins\PageBuilder\LayoutTranslation\LayoutTranslationConfig;
use Plugins\PageBuilder\LayoutTranslation\Persistence\Contracts\LayoutTranslationBindingReaderInterface;
use Plugins\PageBuilder\Ownership\LayoutOwnerRegistry;
use Plugins\PageBuilder\Support\LayoutStore;

/**
 * Read-only, deterministic clone eligibility policy (LT-3).
 *
 * Layer: Translation (eligibility) — a distinct layer from Capability (consumed
 * here), Authorization, and Workflow. It returns a typed
 * {@see CloneEligibilityResult}; it never mutates, persists, clones, or
 * synchronizes. Note the layering: it CONSUMES capability metadata but never
 * derives it.
 */
final class CloneEligibilityEvaluator
{
    public function __construct(
        private readonly LayoutTranslationConfig $config,
        private readonly LayoutTranslationCapabilityResolverInterface $capabilities,
        private readonly TargetOwnerResolverInterface $targetOwners,
        private readonly SourceLayoutDocumentReaderInterface $source,
        private readonly LayoutTranslationBindingReaderInterface $bindings,
        private readonly LayoutStore $layouts,
        private readonly LayoutOwnerRegistry $owners,
    ) {}

    public function evaluate(CloneLayoutTranslationCommand $command): CloneEligibilityResult
    {
        if (! $this->config->enabled()) {
            return CloneEligibilityResult::ineligible(CloneIneligibilityReason::FeatureDisabled);
        }

        if (! $this->capabilities->capabilitiesFor()->supportsClone()) {
            return CloneEligibilityResult::ineligible(CloneIneligibilityReason::CapabilityUnsupported);
        }

        if ($command->isSameTranslation()) {
            return CloneEligibilityResult::ineligible(CloneIneligibilityReason::SameTranslation);
        }

        if (! $command->isSameGroup()) {
            return CloneEligibilityResult::ineligible(CloneIneligibilityReason::DifferentTranslationGroup);
        }

        if (! $command->isCanonical()) {
            return CloneEligibilityResult::ineligible(CloneIneligibilityReason::InvalidIdentity);
        }

        $targetOwner = $this->targetOwners->resolve($command->target);
        if ($targetOwner === null) {
            return CloneEligibilityResult::ineligible(CloneIneligibilityReason::TargetNotFound);
        }

        if (! $this->owners->isRegistered($targetOwner->ownerType())) {
            return CloneEligibilityResult::ineligible(CloneIneligibilityReason::IntegrityFault);
        }

        $sourceRead = $this->source->read($command->source);
        if ($sourceRead->isNotFound()) {
            return CloneEligibilityResult::ineligible(CloneIneligibilityReason::SourceNotFound);
        }
        if ($sourceRead->isPayloadUnavailable()) {
            return CloneEligibilityResult::ineligible(CloneIneligibilityReason::SourcePayloadUnavailable);
        }

        if ($this->bindings->findByPageTranslationKey($command->target) !== null) {
            return CloneEligibilityResult::ineligible(CloneIneligibilityReason::TargetAlreadyBound);
        }

        if ($this->layouts->find($targetOwner->ownerType(), $targetOwner->ownerId()) !== null) {
            return CloneEligibilityResult::ineligible(CloneIneligibilityReason::TargetLayoutConflict);
        }

        return CloneEligibilityResult::eligible();
    }
}
