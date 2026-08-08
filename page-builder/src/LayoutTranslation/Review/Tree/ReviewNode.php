<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Review\Tree;

use Plugins\PageBuilder\LayoutTranslation\Diff\NodeChangeType;
use Plugins\PageBuilder\LayoutTranslation\Plan\ConflictReason;
use Plugins\PageBuilder\LayoutTranslation\Plan\SynchronizationClassification;
use Plugins\PageBuilder\LayoutTranslation\Plan\SynchronizationDecision;

/**
 * One read-only review node — a projection of a plan item (LT-8B, per
 * LT-8A-REVIEW-WORKSPACE-ARCHITECTURE §3.1 and INV-LT-8A-08).
 *
 * Layer: Translation (review projection / value). QUERY side only: it REPORTS an origin's plan facts and
 * its `effectiveDecision`/`decided` state; it cannot change them. It exposes no apply()/save()/accept()/
 * reject() — reviewer intent lives only in the DecisionSet. Payload-safe: origins + enums only.
 */
final class ReviewNode
{
    public function __construct(
        public readonly string $origin,
        public readonly NodeChangeType $sourceChange,
        public readonly SynchronizationClassification $classification,
        public readonly ?ConflictReason $conflictReason,
        public readonly SynchronizationDecision $effectiveDecision,
        public readonly bool $decided,
    ) {}

    public function isSafe(): bool
    {
        return $this->classification === SynchronizationClassification::Safe;
    }

    public function needsReview(): bool
    {
        return ! $this->isSafe();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'origin' => $this->origin,
            'source_change' => $this->sourceChange->value,
            'classification' => $this->classification->value,
            'conflict_reason' => $this->conflictReason?->value,
            'effective_decision' => $this->effectiveDecision->value,
            'decided' => $this->decided,
        ];
    }
}
