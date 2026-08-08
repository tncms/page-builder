<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Recovery;

/**
 * The immutable, confirmed request to execute a recovery (LT-9D, per INV-LT-9D-02).
 *
 * Layer: Translation (recovery / command). It embeds the exact {@see RecoveryPlan} that was confirmed, the
 * plan fingerprint captured at confirmation time (so execution can prove the plan was not swapped —
 * INV-LT-9D-05), an explicit confirmation flag, whether an override was granted, and an optional actor. It
 * carries NO plan-building authority and NO execution authority itself: the workflow validates it and then
 * delegates to the frozen Coordinator (INV-LT-9D-03). There is no way to construct one without a plan.
 */
final class RecoveryExecutionRequest
{
    private function __construct(
        public readonly RecoveryPlan $plan,
        public readonly string $planFingerprint,
        public readonly bool $confirmed,
        public readonly bool $overrideGranted,
        public readonly ?string $actor,
    ) {}

    /**
     * Confirm a plan for execution. The fingerprint is captured from the plan itself, so a valid confirmed
     * request always matches its plan; validation rejects any tampered fingerprint.
     */
    public static function confirm(RecoveryPlan $plan, ?string $actor = null, bool $overrideGranted = false): self
    {
        return new self($plan, $plan->fingerprint(), true, $overrideGranted, $actor);
    }

    /**
     * Construct with an explicit fingerprint — used by tests and adapters to exercise the tamper guard.
     */
    public static function of(RecoveryPlan $plan, string $planFingerprint, bool $confirmed, bool $overrideGranted, ?string $actor): self
    {
        return new self($plan, $planFingerprint, $confirmed, $overrideGranted, $actor);
    }

    public function fingerprintMatches(): bool
    {
        return hash_equals($this->plan->fingerprint(), $this->planFingerprint);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'plan_fingerprint' => $this->planFingerprint,
            'confirmed' => $this->confirmed,
            'override_granted' => $this->overrideGranted,
            'actor' => $this->actor,
            'plan' => $this->plan->toArray(),
        ];
    }
}
