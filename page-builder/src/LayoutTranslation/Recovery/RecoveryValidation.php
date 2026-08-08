<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Recovery;

/**
 * The deterministic validation of a confirmed recovery execution request (LT-9D, per INV-LT-9D-02/03).
 *
 * Layer: Translation (recovery / validation). It is the gate the workflow runs BEFORE delegating to the
 * frozen Coordinator. It refuses any request that: was never confirmed; carries a plan whose decision is
 * Blocked; requires an override that was not granted; carries a reserved or non-executing strategy; or whose
 * fingerprint no longer matches its embedded plan (tamper / non-determinism guard). Read-only and pure —
 * identical inputs produce the identical verdict.
 */
final class RecoveryValidation
{
    private function __construct(
        private readonly bool $valid,
        private readonly ?string $reason,
    ) {}

    public static function valid(): self
    {
        return new self(true, null);
    }

    public static function invalid(string $reason): self
    {
        return new self(false, $reason);
    }

    /**
     * Validate a confirmed request against its embedded plan. Total and deterministic.
     */
    public static function for(RecoveryExecutionRequest $request): self
    {
        if (! $request->fingerprintMatches()) {
            return self::invalid('plan_fingerprint_mismatch');
        }
        if (! $request->confirmed) {
            return self::invalid('confirmation_required');
        }

        $plan = $request->plan;

        if ($plan->strategy->isReserved()) {
            return self::invalid('mode_reserved');
        }
        if (! $plan->strategy->isExecuting()) {
            return self::invalid('mode_not_executable');
        }
        if (! $plan->decision->canProceed($request->overrideGranted)) {
            return self::invalid($plan->decision === RecoveryDecision::Blocked ? 'blocked' : 'override_required');
        }

        return self::valid();
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function reason(): ?string
    {
        return $this->reason;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['valid' => $this->valid, 'reason' => $this->reason];
    }
}
