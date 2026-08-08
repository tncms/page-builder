<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Execution\Diagnostics;

use Plugins\PageBuilder\LayoutTranslation\Baseline\BaselineFingerprint;
use Plugins\PageBuilder\LayoutTranslation\Execution\Contracts\ExecutionEligibilityInterface;
use Plugins\PageBuilder\LayoutTranslation\Execution\Contracts\SynchronizationStateLoaderInterface;
use Plugins\PageBuilder\LayoutTranslation\Identity\PageTranslationKey;
use Throwable;

/**
 * Read-only introspection for a synchronization target (LT-7B3, per LT-7-EXECUTION-ARCHITECTURE §12).
 *
 * Layer: Translation (execution diagnostics). Never-throws, non-repairing, and side-effect-free — it
 * reports whether an execution WOULD be blocked and whether the source is ahead of the baseline, using a
 * lock-free read. It mutates nothing and emits no payloads (fingerprints and booleans only). It is the
 * read-only surface for the review UI (LT-8) and support tooling; it is strictly separate from execution.
 */
final class SynchronizationExecutionDiagnostics
{
    public function __construct(
        private readonly ExecutionEligibilityInterface $eligibility,
        private readonly SynchronizationStateLoaderInterface $loader,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function report(PageTranslationKey $target): array
    {
        try {
            $featureEnabled = $this->eligibility->featureEnabled();
            $supportsSync = $this->eligibility->supportsSync($target);
            $state = $this->loader->load($target);

            $sourceAhead = false;
            if ($state->hasBaseline() && $state->hasSource()) {
                $sourceAhead = BaselineFingerprint::of($state->sourcePayload()) !== $state->baselineFingerprint();
            }

            return [
                'feature_enabled' => $featureEnabled,
                'supports_sync' => $supportsSync,
                'bound' => $state->isBound(),
                'has_baseline' => $state->hasBaseline(),
                'source_available' => $state->hasSource(),
                'target_available' => $state->hasTarget(),
                'source_ahead_of_baseline' => $sourceAhead,
                'would_execute' => $featureEnabled && $supportsSync && $state->isBound()
                    && $state->hasBaseline() && $state->hasSource() && $state->hasTarget(),
                'error' => false,
            ];
        } catch (Throwable) {
            return [
                'feature_enabled' => false,
                'supports_sync' => false,
                'bound' => false,
                'has_baseline' => false,
                'source_available' => false,
                'target_available' => false,
                'source_ahead_of_baseline' => false,
                'would_execute' => false,
                'error' => true,
            ];
        }
    }
}
