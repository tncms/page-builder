<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Recovery;

/**
 * The pure, deterministic recovery eligibility evaluator (LT-9D, per INV-LT-9D-05).
 *
 * Layer: Translation (recovery / eligibility). It decides whether an execution MAY be recovered, reading
 * ONLY the immutable {@see RecoveryCandidate} plus the {@see RecoveryPolicy} and {@see RecoveryRequest}. It
 * has no collaborators, touches no storage, and never executes — identical inputs always produce the
 * identical {@see RecoveryEligibility}. Mode-independent faults (missing entry, feature/capability/version,
 * superseded, broken chain, missing baseline) refuse every strategy; mode-specific faults (policy,
 * permission, replay/snapshot feasibility) refuse the requested strategy while still surfacing which
 * strategies remain supportable.
 */
final class RecoveryEligibilityEvaluator
{
    public function evaluate(RecoveryCandidate $candidate, RecoveryPolicy $policy, RecoveryRequest $request): RecoveryEligibility
    {
        if (! $candidate->entryExists) {
            return RecoveryEligibility::ineligible([RecoveryIneligibilityReason::EntryNotFound]);
        }

        // Mode-independent faults refuse every strategy.
        $hard = [];
        if (! $candidate->featureEnabled) {
            $hard[] = RecoveryIneligibilityReason::FeatureDisabled;
        }
        if (! $candidate->capabilitySupported) {
            $hard[] = RecoveryIneligibilityReason::CapabilityUnsupported;
        }
        if (! $candidate->versionSupported) {
            $hard[] = RecoveryIneligibilityReason::UnsupportedVersion;
        }
        if (! $candidate->isTail()) {
            $hard[] = RecoveryIneligibilityReason::AlreadySuperseded;
        }
        if (! $candidate->chainContinuous) {
            $hard[] = RecoveryIneligibilityReason::BrokenChain;
        }
        if (! $candidate->hasBaseline()) {
            $hard[] = RecoveryIneligibilityReason::MissingBaseline;
        }
        if ($hard !== []) {
            return RecoveryEligibility::ineligible($hard);
        }

        $supported = $this->supportedModes($candidate, $policy);

        // Mode-specific faults refuse the requested strategy but keep the supportable set visible.
        $reasons = [];
        if (! $policy->allows($request->mode)) {
            $reasons[] = $request->mode->isReserved()
                ? RecoveryIneligibilityReason::ModeReserved
                : RecoveryIneligibilityReason::PolicyDenied;
        } elseif (! $this->modeFeasible($request->mode, $candidate)) {
            $reasons[] = $this->infeasibilityReason($request->mode, $candidate);
        }

        foreach ($this->requiredPermissions($request->mode) as $permission) {
            if (! $request->grants($permission)) {
                $reasons[] = RecoveryIneligibilityReason::PermissionDenied;
                break;
            }
        }

        return $reasons === []
            ? RecoveryEligibility::eligible($supported)
            : RecoveryEligibility::ineligible($reasons, $supported);
    }

    /**
     * @return array<int, RecoveryMode>
     */
    private function supportedModes(RecoveryCandidate $candidate, RecoveryPolicy $policy): array
    {
        $supported = [];
        foreach ($policy->allowedModes() as $mode) {
            if ($policy->allows($mode) && $this->modeFeasible($mode, $candidate)) {
                $supported[] = $mode;
            }
        }

        return $supported;
    }

    private function modeFeasible(RecoveryMode $mode, RecoveryCandidate $candidate): bool
    {
        return match ($mode) {
            RecoveryMode::ReplayOnly => ! $candidate->replayUnavailable(),
            RecoveryMode::ReExecute, RecoveryMode::Retry => $candidate->hasReExecutionSnapshots(),
            RecoveryMode::Manual => true,
            RecoveryMode::AiAssisted => false,
        };
    }

    private function infeasibilityReason(RecoveryMode $mode, RecoveryCandidate $candidate): RecoveryIneligibilityReason
    {
        return match ($mode) {
            RecoveryMode::ReplayOnly => RecoveryIneligibilityReason::ReplayUnavailable,
            RecoveryMode::ReExecute, RecoveryMode::Retry => RecoveryIneligibilityReason::MissingSnapshot,
            RecoveryMode::AiAssisted => RecoveryIneligibilityReason::ModeReserved,
            RecoveryMode::Manual => RecoveryIneligibilityReason::ModeInfeasible,
        };
    }

    /**
     * @return array<int, string>
     */
    private function requiredPermissions(RecoveryMode $mode): array
    {
        $permissions = ['timeline.recovery.view', 'timeline.recovery.plan'];
        if ($mode->executePermission() !== null) {
            $permissions[] = $mode->executePermission();
        }

        return $permissions;
    }
}
