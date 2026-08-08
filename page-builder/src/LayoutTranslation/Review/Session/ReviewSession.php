<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Review\Session;

use Plugins\PageBuilder\LayoutTranslation\Execution\Results\SynchronizationExecutionResult;
use Plugins\PageBuilder\LayoutTranslation\Plan\SynchronizationDecision;
use Plugins\PageBuilder\LayoutTranslation\Review\Approval\ReviewApprovalService;
use Plugins\PageBuilder\LayoutTranslation\Review\Contracts\PreviewProjectionInterface;
use Plugins\PageBuilder\LayoutTranslation\Review\Decision\DecisionBinding;
use Plugins\PageBuilder\LayoutTranslation\Review\Decision\DecisionSet;
use Plugins\PageBuilder\LayoutTranslation\Review\Navigation\ReviewNavigator;
use Plugins\PageBuilder\LayoutTranslation\Review\Navigation\SelectionModel;
use Plugins\PageBuilder\LayoutTranslation\Review\Preview\PreviewBinding;
use Plugins\PageBuilder\LayoutTranslation\Review\Preview\PreviewFragment;
use Plugins\PageBuilder\LayoutTranslation\Review\Subject\ReviewSubject;
use Plugins\PageBuilder\LayoutTranslation\Review\Tree\ReviewTree;
use Plugins\PageBuilder\LayoutTranslation\Review\Tree\ReviewTreeProjector;

/**
 * The review session aggregate (LT-8B, per LT-8A-REVIEW-WORKSPACE-ARCHITECTURE §2 and
 * LT-8A-FINAL-GOVERNANCE INV-LT-8A-07/08).
 *
 * Layer: Translation (review runtime). It orchestrates the whole review: lifecycle state, the working
 * {@see DecisionSet} (the only authoritative reviewer intent), selection, navigation, preview binding, and
 * the single submit hand-off to the frozen coordinator. It is DISPOSABLE and never a source of truth
 * (INV-LT-8A-07): the tree, selection, navigation results, and preview fragments are all recomputed on
 * demand from (plan, decisions) and may be discarded and rebuilt at any time. Its query surfaces expose no
 * mutator (INV-LT-8A-08); intent is written only through the guarded {@see decide()} path. It computes
 * none of the diff/plan/execution it presents (INV-LT-8A-01).
 */
final class ReviewSession
{
    private readonly ReviewTreeProjector $projector;

    private readonly ReviewNavigator $navigator;

    private readonly DecisionBinding $decisionBinding;

    private function __construct(
        private readonly ReviewSessionId $id,
        private readonly ReviewSubject $subject,
        private readonly DecisionSet $decisions,
        private readonly SelectionModel $selection,
        private readonly PreviewBinding $preview,
        private readonly ReviewApprovalService $approval,
        private ReviewSessionState $state,
    ) {
        $this->projector = new ReviewTreeProjector();
        $this->navigator = new ReviewNavigator();
        $this->decisionBinding = new DecisionBinding($decisions);
    }

    /**
     * Open a fresh review: seed fail-closed decisions from the plan (INV-LT-8A-05) and focus the first
     * item needing review (or the first item).
     */
    public static function open(
        ReviewSessionId $id,
        ReviewSubject $subject,
        PreviewProjectionInterface $preview,
        ReviewApprovalService $approval,
    ): self {
        $decisions = DecisionSet::seedFromPlan($subject->plan);
        $session = new self(
            $id,
            $subject,
            $decisions,
            SelectionModel::empty(),
            new PreviewBinding($preview, $subject->target),
            $approval,
            ReviewSessionState::InReview,
        );

        $tree = $session->tree();
        $focus = $session->navigator->firstUnreviewed($tree) ?? $session->navigator->first($tree);
        if ($focus !== null) {
            $session->selection->select($focus);
        }

        return $session;
    }

    /**
     * Reconstruct a session from its two authoritative inputs (INV-LT-8A-07). The plan lives in the
     * subject (re-derived deterministically upstream); the decisions are supplied. Selection is
     * disposable and restored best-effort.
     */
    public static function reconstruct(
        ReviewSessionId $id,
        ReviewSubject $subject,
        DecisionSet $decisions,
        PreviewProjectionInterface $preview,
        ReviewApprovalService $approval,
        ?string $selectedOrigin = null,
    ): self {
        $selection = $selectedOrigin !== null && $decisions->has($selectedOrigin)
            ? SelectionModel::at($selectedOrigin)
            : SelectionModel::empty();

        return new self(
            $id,
            $subject,
            $decisions,
            $selection,
            new PreviewBinding($preview, $subject->target),
            $approval,
            ReviewSessionState::InReview,
        );
    }

    /**
     * Resume a paused session against a freshly re-derived subject (LT-8A §8.2). Fail-closed: if the
     * retained provenance drifted, the working decisions are discarded and the caller must re-review
     * (INV-LT-8A-04/07).
     */
    public static function resume(
        ReviewSessionSnapshot $snapshot,
        ReviewSubject $current,
        PreviewProjectionInterface $preview,
        ReviewApprovalService $approval,
    ): self {
        if ($snapshot->provenance->toArray() !== $current->provenance->toArray()) {
            throw StaleReviewSessionException::driftedProvenance();
        }

        $decisions = DecisionSet::reconstruct($current->plan, $snapshot->decisions);

        return self::reconstruct(
            ReviewSessionId::of($snapshot->id),
            $current,
            $decisions,
            $preview,
            $approval,
            $snapshot->selectedOrigin,
        );
    }

    public function id(): ReviewSessionId
    {
        return $this->id;
    }

    public function state(): ReviewSessionState
    {
        return $this->state;
    }

    public function subject(): ReviewSubject
    {
        return $this->subject;
    }

    /**
     * The working decisions — read/lower only. Writes go through {@see decide()} so lifecycle guards hold.
     */
    public function decisions(): DecisionSet
    {
        return $this->decisions;
    }

    /**
     * Project the review tree fresh from (plan, decisions). Disposable — never cached as truth
     * (INV-LT-8A-07).
     */
    public function tree(): ReviewTree
    {
        return $this->projector->project($this->subject->plan, $this->decisions);
    }

    // -- Command side (guarded) ------------------------------------------------------------------------

    public function decide(string $origin, SynchronizationDecision $decision): void
    {
        $this->assertEditable('decide in');
        $this->decisionBinding->record($origin, $decision);
    }

    public function resetDecision(string $origin): void
    {
        $this->assertEditable('reset a decision in');
        $this->decisionBinding->resetToDefault($origin);
    }

    // -- Selection & navigation (disposable) -----------------------------------------------------------

    public function select(string $origin): void
    {
        $this->selection->select($origin);
    }

    public function selected(): ?string
    {
        return $this->selection->current();
    }

    public function selectNext(): ?string
    {
        return $this->moveSelection($this->navigator->next($this->tree(), (string) $this->selection->current()));
    }

    public function selectPrevious(): ?string
    {
        return $this->moveSelection($this->navigator->previous($this->tree(), (string) $this->selection->current()));
    }

    public function selectNextUnreviewed(): ?string
    {
        $tree = $this->tree();
        $current = $this->selection->current();
        $next = $current !== null
            ? $this->navigator->nextUnreviewed($tree, $current)
            : $this->navigator->firstUnreviewed($tree);

        return $this->moveSelection($next);
    }

    // -- Preview (query side, one-directional) ---------------------------------------------------------

    public function preview(string $origin): PreviewFragment
    {
        return $this->preview->fragmentFor($origin);
    }

    // -- Lifecycle -------------------------------------------------------------------------------------

    public function pause(): ReviewSessionSnapshot
    {
        $this->assertEditable('pause');
        $this->state = ReviewSessionState::Paused;

        return $this->snapshot();
    }

    public function snapshot(): ReviewSessionSnapshot
    {
        return new ReviewSessionSnapshot(
            $this->id->value,
            $this->subject->target->value(),
            $this->subject->provenance,
            $this->decisions->snapshot(),
            $this->selection->current(),
        );
    }

    /**
     * Freeze decisions and hand off to the frozen coordinator exactly once. The coordinator is the sole
     * authority (INV-LT-8A-04); the returned typed result is mapped to a terminal state.
     */
    public function submit(?string $actor = null): SynchronizationExecutionResult
    {
        $this->assertEditable('submit');
        $this->state = ReviewSessionState::Submitting;

        $result = $this->approval->submit($this->subject, $this->decisions, $actor);
        $this->state = ReviewApprovalService::terminalStateFor($result);

        return $result;
    }

    public function abandon(): void
    {
        if ($this->state->isTerminal()) {
            throw ReviewSessionException::cannot('abandon', $this->state);
        }
        $this->state = ReviewSessionState::Abandoned;
    }

    private function moveSelection(?string $origin): ?string
    {
        if ($origin !== null) {
            $this->selection->select($origin);
        }

        return $origin;
    }

    private function assertEditable(string $action): void
    {
        if (! $this->state->isWorking()) {
            throw ReviewSessionException::cannot($action, $this->state);
        }
    }
}
