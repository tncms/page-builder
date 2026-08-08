<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Recovery;

/**
 * The compact, payload-safe summary of a recovery plan (LT-9D).
 *
 * Layer: Translation (recovery / summary). A small header for presentation and audit: the entry, the
 * strategy, the reason, the decision, and conflict counts. It derives entirely from an immutable
 * {@see RecoveryPlan} and carries no document content.
 */
final class RecoverySummary
{
    private function __construct(
        public readonly int $entryId,
        public readonly string $pageTranslationKey,
        public readonly RecoveryDecision $decision,
        public readonly RecoveryMode $strategy,
        public readonly RecoveryReason $reason,
        public readonly bool $eligible,
        public readonly int $conflictCount,
        public readonly int $blockingConflictCount,
        public readonly bool $requiresConfirmation,
    ) {}

    public static function of(RecoveryPlan $plan): self
    {
        $blocking = 0;
        foreach ($plan->conflicts as $conflict) {
            if ($conflict->isBlocking()) {
                $blocking++;
            }
        }

        return new self(
            $plan->entryId,
            $plan->pageTranslationKey,
            $plan->decision,
            $plan->strategy,
            $plan->reason,
            $plan->eligibility->isEligible(),
            count($plan->conflicts),
            $blocking,
            $plan->requiresConfirmation(),
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
            'decision' => $this->decision->value,
            'strategy' => $this->strategy->value,
            'reason' => $this->reason->value,
            'eligible' => $this->eligible,
            'conflict_count' => $this->conflictCount,
            'blocking_conflict_count' => $this->blockingConflictCount,
            'requires_confirmation' => $this->requiresConfirmation,
        ];
    }
}
