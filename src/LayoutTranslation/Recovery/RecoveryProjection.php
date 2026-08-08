<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Recovery;

/**
 * The immutable, payload-safe read projection of a recovery plan for presentation (LT-9D).
 *
 * Layer: Translation (recovery / projection). It composes the candidate's observation facts with the plan's
 * verdict into one flat, content-free view — the analogue of a timeline projection. It is derived on demand
 * and never persisted; it re-injects nothing upstream and mutates nothing.
 */
final class RecoveryProjection
{
    /**
     * @param  array<int, string>  $ineligibilityReasons
     * @param  array<int, array<string, mixed>>  $conflicts
     * @param  array<string, bool>  $expectedImpact
     * @param  array<int, string>  $requiredPermissions
     */
    private function __construct(
        public readonly int $entryId,
        public readonly string $pageTranslationKey,
        public readonly ?string $actor,
        public readonly string $executedAt,
        public readonly string $replayStatus,
        public readonly RecoveryDecision $decision,
        public readonly RecoveryMode $strategy,
        public readonly RecoveryReason $reason,
        public readonly bool $eligible,
        public readonly array $ineligibilityReasons,
        public readonly array $conflicts,
        public readonly array $expectedImpact,
        public readonly array $requiredPermissions,
    ) {}

    public static function of(RecoveryCandidate $candidate, RecoveryPlan $plan): self
    {
        return new self(
            $candidate->entryId->value,
            $plan->pageTranslationKey,
            $candidate->actor,
            $candidate->executedAt,
            $candidate->replayStatus->value,
            $plan->decision,
            $plan->strategy,
            $plan->reason,
            $plan->eligibility->isEligible(),
            array_map(static fn (RecoveryIneligibilityReason $r): string => $r->value, $plan->eligibility->reasons()),
            array_map(static fn (RecoveryConflict $c): array => $c->toArray(), $plan->conflicts),
            $plan->expectedImpact,
            $plan->requiredPermissions,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'entry_id' => $this->entryId,
            'page_translation_key' => $this->pageTranslationKey,
            'actor' => $this->actor,
            'executed_at' => $this->executedAt,
            'replay_status' => $this->replayStatus,
            'decision' => $this->decision->value,
            'strategy' => $this->strategy->value,
            'reason' => $this->reason->value,
            'eligible' => $this->eligible,
            'ineligibility_reasons' => $this->ineligibilityReasons,
            'conflicts' => $this->conflicts,
            'expected_impact' => $this->expectedImpact,
            'required_permissions' => $this->requiredPermissions,
        ];
    }
}
