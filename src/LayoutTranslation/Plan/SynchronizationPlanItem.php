<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Plan;

use Plugins\PageBuilder\LayoutTranslation\Diff\NodeChangeType;

/**
 * One immutable, reviewable synchronization plan item (LT-6).
 *
 * Layer: Translation (plan / value). A source change, its classification, the
 * fail-closed default decision, and (when not safe) the canonical conflict reason.
 * Payload-safe — origins and enums only, no content.
 */
final class SynchronizationPlanItem
{
    public function __construct(
        public readonly string $origin,
        public readonly NodeChangeType $sourceChange,
        public readonly SynchronizationClassification $classification,
        public readonly SynchronizationDecision $defaultDecision,
        public readonly ?ConflictReason $conflictReason = null,
    ) {}

    public function isSafe(): bool
    {
        return $this->classification === SynchronizationClassification::Safe;
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
            'default_decision' => $this->defaultDecision->value,
            'conflict_reason' => $this->conflictReason?->value,
        ];
    }
}
