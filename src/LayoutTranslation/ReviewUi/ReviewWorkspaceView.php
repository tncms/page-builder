<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\ReviewUi;

use Plugins\PageBuilder\LayoutTranslation\Execution\Results\SynchronizationExecutionResult;
use Plugins\PageBuilder\LayoutTranslation\Plan\SynchronizationDecision;
use Plugins\PageBuilder\LayoutTranslation\Review\Session\ReviewSession;
use Plugins\PageBuilder\LayoutTranslation\Review\Tree\ReviewGroup;
use Plugins\PageBuilder\LayoutTranslation\Review\Tree\ReviewNode;

/**
 * Builds read-only view models for the review presentation (LT-8C, per LT-8-PRESENTATION-GOVERNANCE
 * INV-LT-8-PRES-02 and INV-LT-8A-08).
 *
 * Layer: Translation (review presentation / read model). Framework-free. It maps the frozen, read-only
 * projections ({@see ReviewSession}, {@see \Plugins\PageBuilder\LayoutTranslation\Review\Tree\ReviewTree},
 * {@see \Plugins\PageBuilder\LayoutTranslation\Review\Preview\PreviewFragment}, {@see
 * SynchronizationExecutionResult}) into plain arrays Blade can render. It NEVER mutates a projection, never
 * decides policy, and never constructs acceptance/commands — it only presents. Preview is obtained solely
 * through the runtime's preview binding (the one `PreviewProjectionInterface` path) and fails soft.
 */
final class ReviewWorkspaceView
{
    /**
     * The full workspace view model (tree, counts, lifecycle state, selection).
     *
     * @return array<string, mixed>
     */
    public function workspace(ReviewSession $session): array
    {
        $tree = $session->tree();

        return [
            'session_id' => $session->id()->value,
            'target' => $session->subject()->target->value(),
            'state' => $session->state()->value,
            'summary' => $session->subject()->plan->summary(),
            'undecided_review_count' => $tree->undecidedReviewCount(),
            'selected_origin' => $session->selected(),
            'groups' => array_map(fn (ReviewGroup $g): array => $this->group($g), $tree->groups()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function group(ReviewGroup $group): array
    {
        return [
            'classification' => $group->classification->value,
            'count' => $group->count(),
            'nodes' => array_map(fn (ReviewNode $n): array => $this->node($n), $group->nodes),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function node(ReviewNode $node): array
    {
        // The decision OPTIONS are the frozen SynchronizationDecision cases — a presentation list, not
        // decision logic (the runtime remains the sole authority for what a decision means).
        $options = array_map(
            static fn (SynchronizationDecision $d): string => $d->value,
            SynchronizationDecision::cases(),
        );

        return $node->toArray() + ['options' => $options];
    }

    /**
     * The Conflict Panel preview view model for one origin. Content is obtained ONLY through the runtime's
     * preview binding ({@see PreviewProjectionInterface}); an unavailable fragment renders fail-soft with no
     * alternate content path (INV-LT-8-PRES-02).
     *
     * @return array<string, mixed>
     */
    public function preview(ReviewSession $session, string $origin): array
    {
        $fragment = $session->preview($origin);

        if (! $fragment->isAvailable()) {
            return [
                'origin' => $fragment->origin,
                'change' => $fragment->change->value,
                'available' => false,
                'unavailable_reason' => $fragment->availability->value,
            ];
        }

        return [
            'origin' => $fragment->origin,
            'change' => $fragment->change->value,
            'available' => true,
            'baseline' => $fragment->baseline?->render,
            'source' => $fragment->source?->render,
            'target' => $fragment->target?->render,
        ];
    }

    /**
     * The typed execution-result view model (submit outcome presentation).
     *
     * @return array<string, mixed>
     */
    public function result(SynchronizationExecutionResult $result): array
    {
        return [
            'outcome' => $result->outcome->value,
            'successful' => $result->isSuccessful(),
            'mutated' => $result->didMutate(),
            'applied_count' => $result->appliedCount,
            'skipped_count' => $result->skippedCount,
            'new_baseline_fingerprint' => $result->newBaselineFingerprint,
            'history_id' => $result->historyId,
            'detail' => $result->detail,
        ];
    }
}
