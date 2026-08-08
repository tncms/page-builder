<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Plan;

use Plugins\PageBuilder\LayoutTranslation\Diff\NodeChangeType;
use Plugins\PageBuilder\LayoutTranslation\Diff\ThreeWayStructuralDiff;

/**
 * Builds a reviewable synchronization plan from a three-way diff (LT-6, per
 * LT-0-ADR-001 D9/D10).
 *
 * Layer: Translation (plan). Stateless, deterministic, read-only: it classifies each
 * source change against target customizations and assigns a fail-closed default
 * decision. It NEVER mutates the target — the plan is a preview; selection and
 * execution are LT-7.
 *
 * Policy: a source change to a node the target did not touch is Safe (AcceptSource).
 * A node changed on both sides never auto-overwrites the target — a content divergence
 * is ReviewRequired and a structural/deletion clash is a Conflict; both default to
 * KeepTarget. Target-only customizations are preserved (never in the plan).
 */
final class SynchronizationPlanner
{
    public function plan(ThreeWayStructuralDiff $diff): SynchronizationPlan
    {
        $items = [];

        foreach ($diff->sourceDiff()->changes() as $sourceChange) {
            $targetChange = $diff->targetDiff()->typeFor($sourceChange->origin);

            if ($targetChange === null) {
                $items[] = new SynchronizationPlanItem(
                    $sourceChange->origin,
                    $sourceChange->type,
                    SynchronizationClassification::Safe,
                    SynchronizationDecision::AcceptSource,
                );

                continue;
            }

            [$classification, $reason] = $this->classifyConflict($sourceChange->type, $targetChange);

            $items[] = new SynchronizationPlanItem(
                $sourceChange->origin,
                $sourceChange->type,
                $classification,
                SynchronizationDecision::KeepTarget,
                $reason,
            );
        }

        return new SynchronizationPlan($items);
    }

    /**
     * @return array{0: SynchronizationClassification, 1: ConflictReason}
     */
    private function classifyConflict(NodeChangeType $source, NodeChangeType $target): array
    {
        if ($source === NodeChangeType::ContentUpdated && $target === NodeChangeType::ContentUpdated) {
            return [SynchronizationClassification::ReviewRequired, ConflictReason::TranslatedContentDiverged];
        }

        if ($source === NodeChangeType::Removed) {
            return [SynchronizationClassification::Conflict, ConflictReason::SourceRemovedTargetModified];
        }

        return [SynchronizationClassification::Conflict, ConflictReason::StructuralConflict];
    }
}
