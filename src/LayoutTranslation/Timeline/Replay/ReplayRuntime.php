<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Timeline\Replay;

use Plugins\PageBuilder\LayoutTranslation\Baseline\BaselineFingerprint;
use Plugins\PageBuilder\LayoutTranslation\Diff\StructuralDiffEngine;
use Plugins\PageBuilder\LayoutTranslation\Execution\Acceptance\AcceptedItemDecision;
use Plugins\PageBuilder\LayoutTranslation\Execution\Acceptance\SynchronizationAcceptanceSet;
use Plugins\PageBuilder\LayoutTranslation\Execution\Contracts\OperationApplierInterface;
use Plugins\PageBuilder\LayoutTranslation\Execution\Operations\OperationBuilder;
use Plugins\PageBuilder\LayoutTranslation\Plan\SynchronizationDecision;
use Plugins\PageBuilder\LayoutTranslation\Plan\SynchronizationPlan;
use Plugins\PageBuilder\LayoutTranslation\Plan\SynchronizationPlanner;
use Plugins\PageBuilder\LayoutTranslation\Timeline\Entry\TimelineEntry;
use Plugins\PageBuilder\LayoutTranslation\Timeline\Resolution\TimelineDocumentResolver;
use Plugins\PageBuilder\Package\DesignPackageManifest;
use Throwable;

/**
 * The read-only replay runtime (LT-9B, per LT-9A §3, INV-LT-9B-RT-02).
 *
 * Layer: Translation (timeline / replay). Reconstructs a past sync's inputs from snapshots (baseline,
 * source, target-before), re-derives the plan with the FROZEN LT-5 diff + LT-6 planner, rebuilds the
 * reviewer acceptance from the history decisions, and re-applies through the FROZEN pure mutation engine —
 * then compares the recomputed target to the retained target-after snapshot. It is strictly OBSERVATIONAL:
 * it writes nothing, never persists, and never executes (execution belongs to the Coordinator). Missing
 * snapshots fail soft (`Unavailable`); a mismatch is `Diverged`; a match is `Reproduced`.
 */
final class ReplayRuntime
{
    public function __construct(
        private readonly TimelineDocumentResolver $documents,
        private readonly StructuralDiffEngine $diff,
        private readonly SynchronizationPlanner $planner,
        private readonly OperationBuilder $operations,
        private readonly OperationApplierInterface $applier,
    ) {}

    public function replay(TimelineEntry $entry): ReplayResult
    {
        $baseline = $this->documents->baselineBefore($entry);
        $source = $this->documents->source($entry);
        $targetBefore = $this->documents->targetBefore($entry);

        if ($baseline === null || $source === null || $targetBefore === null) {
            return ReplayResult::unavailable($entry->id, 'inputs_unavailable');
        }

        // Re-derive the plan deterministically from the reconstructed inputs (frozen LT-5 + LT-6).
        $plan = $this->planner->plan($this->diff->threeWay($baseline, $source, $targetBefore));

        $actualPlanFingerprint = $this->planFingerprint($plan);
        if (! hash_equals($entry->planFingerprint, $actualPlanFingerprint)) {
            return ReplayResult::diverged($entry->id, ReplayDifference::planFingerprintMismatch($entry->planFingerprint, $actualPlanFingerprint));
        }

        // Rebuild the acceptance from the recorded decisions and re-apply through the frozen pure engine.
        try {
            $operations = $this->operations->build($plan, $this->reconstructAcceptance($entry), $source);
            $recomputed = $this->applier->apply($targetBefore, $operations);
        } catch (Throwable $e) {
            return ReplayResult::diverged($entry->id, ReplayDifference::applyFault($this->faultReason($e)));
        }

        $targetAfter = $this->documents->targetAfter($entry);
        if ($targetAfter === null) {
            return ReplayResult::unavailable($entry->id, 'target_after_unavailable');
        }

        $expected = BaselineFingerprint::of($targetAfter);
        $actual = BaselineFingerprint::of($recomputed);
        if (hash_equals($expected, $actual)) {
            return ReplayResult::reproduced($entry->id);
        }

        $differing = $this->diff->compare($targetAfter, $recomputed)->changedOrigins();

        return ReplayResult::diverged($entry->id, ReplayDifference::documentMismatch($differing, $expected, $actual));
    }

    private function reconstructAcceptance(TimelineEntry $entry): SynchronizationAcceptanceSet
    {
        $decisions = [];

        foreach ([...$entry->appliedItems, ...$entry->skippedItems] as $item) {
            $origin = (string) ($item['origin'] ?? '');
            $decision = SynchronizationDecision::tryFrom((string) ($item['decision'] ?? ''));

            if ($origin !== '' && $decision !== null) {
                $decisions[] = new AcceptedItemDecision($origin, $decision);
            }
        }

        return new SynchronizationAcceptanceSet($decisions);
    }

    private function planFingerprint(SynchronizationPlan $plan): string
    {
        return BaselineFingerprint::ALGO.'-'.hash(BaselineFingerprint::ALGO, DesignPackageManifest::canonicalJson($plan->toArray()));
    }

    private function faultReason(Throwable $e): string
    {
        $class = $e::class;
        $short = substr($class, (int) strrpos($class, '\\') + 1);

        return strtolower($short);
    }
}
