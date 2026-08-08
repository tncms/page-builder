<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Review\Tree;

use Plugins\PageBuilder\LayoutTranslation\Plan\SynchronizationClassification;
use Plugins\PageBuilder\LayoutTranslation\Plan\SynchronizationPlan;
use Plugins\PageBuilder\LayoutTranslation\Review\Decision\DecisionSet;

/**
 * Projects a {@see ReviewTree} from a plan and the working decisions (LT-8B, per
 * LT-8A-REVIEW-WORKSPACE-ARCHITECTURE §3 and INV-LT-8A-01/07).
 *
 * Layer: Translation (review projection / query). A pure, deterministic function of
 * (SynchronizationPlan, DecisionSet). It COMPUTES NOTHING the pipeline owns — no classification, no
 * re-plan, no diff (INV-LT-8A-01); it re-presents the frozen plan items grouped by review priority and
 * reads the DecisionSet for each node's effective decision. It never mutates the DecisionSet.
 */
final class ReviewTreeProjector
{
    /** @var array<int, SynchronizationClassification> review-priority order */
    private const PRIORITY = [
        SynchronizationClassification::Conflict,
        SynchronizationClassification::ReviewRequired,
        SynchronizationClassification::Safe,
        SynchronizationClassification::Unsupported,
    ];

    public function project(SynchronizationPlan $plan, DecisionSet $decisions): ReviewTree
    {
        /** @var array<string, array<int, ReviewNode>> $byClass */
        $byClass = [];

        foreach ($plan->items() as $item) {
            $effective = $decisions->decisionFor($item->origin) ?? $item->defaultDecision;

            $byClass[$item->classification->value][] = new ReviewNode(
                $item->origin,
                $item->sourceChange,
                $item->classification,
                $item->conflictReason,
                $effective,
                $decisions->isDecided($item->origin),
            );
        }

        $groups = [];
        foreach (self::PRIORITY as $classification) {
            $nodes = $byClass[$classification->value] ?? [];
            if ($nodes !== []) {
                $groups[] = new ReviewGroup($classification, $nodes);
            }
        }

        return ReviewTree::of($groups);
    }
}
