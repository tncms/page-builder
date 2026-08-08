<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Recovery;

/**
 * The immutable, top-level result of a recovery planning pass (LT-9D).
 *
 * Layer: Translation (recovery / report). It aggregates the produced {@see RecoveryPlan}, its
 * {@see RecoverySummary}, and its {@see RecoveryProjection} into the single artifact the workflow returns
 * from planning. It is read-only, carries no document content, and confers no execution authority — a report
 * is confirmed and turned into a {@see RecoveryExecutionRequest}, never executed on its own (INV-LT-9D-02).
 */
final class RecoveryReport
{
    private function __construct(
        public readonly string $pageTranslationKey,
        public readonly int $entryId,
        public readonly RecoveryPlan $plan,
        public readonly RecoverySummary $summary,
        public readonly RecoveryProjection $projection,
    ) {}

    public static function of(RecoveryRequest $request, RecoveryPlan $plan, RecoveryCandidate $candidate): self
    {
        return new self(
            $request->target->value(),
            $candidate->entryId->value,
            $plan,
            RecoverySummary::of($plan),
            RecoveryProjection::of($candidate, $plan),
        );
    }

    public function plan(): RecoveryPlan
    {
        return $this->plan;
    }

    public function summary(): RecoverySummary
    {
        return $this->summary;
    }

    public function projection(): RecoveryProjection
    {
        return $this->projection;
    }

    public function decision(): RecoveryDecision
    {
        return $this->plan->decision;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'page_translation_key' => $this->pageTranslationKey,
            'entry_id' => $this->entryId,
            'plan' => $this->plan->toArray(),
            'summary' => $this->summary->toArray(),
            'projection' => $this->projection->toArray(),
        ];
    }
}
