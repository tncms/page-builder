<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\ReviewUi;

use InvalidArgumentException;
use Plugins\PageBuilder\LayoutTranslation\Execution\Results\SynchronizationExecutionResult;
use Plugins\PageBuilder\LayoutTranslation\Identity\PageTranslationKey;
use Plugins\PageBuilder\LayoutTranslation\Plan\SynchronizationDecision;
use Plugins\PageBuilder\LayoutTranslation\Review\Approval\ReviewApprovalService;
use Plugins\PageBuilder\LayoutTranslation\Review\Contracts\PreviewProjectionInterface;
use Plugins\PageBuilder\LayoutTranslation\Review\Contracts\ReviewInputProviderInterface;
use Plugins\PageBuilder\LayoutTranslation\Review\Contracts\ReviewSessionStoreInterface;
use Plugins\PageBuilder\LayoutTranslation\Review\Session\ReviewSession;
use Plugins\PageBuilder\LayoutTranslation\Review\Session\ReviewSessionId;

/**
 * The single presentation touchpoint to the frozen Workspace Runtime (LT-8C, per
 * LT-8-PRESENTATION-GOVERNANCE INV-LT-8-PRES-01/02).
 *
 * Layer: Translation (review presentation / application service). Framework-free. Every state-changing UI
 * action maps ONE-TO-ONE to a single Workspace Runtime operation here (INV-LT-8-PRES-02); the UI never
 * touches acceptance, commands, or the coordinator. It owns no domain state (INV-LT-8-PRES-01): each call
 * reconstructs the disposable session from the runtime's session store + a freshly re-derived subject
 * (INV-LT-8A-07), applies exactly one runtime op, and persists the payload-safe snapshot back. Provenance
 * drift on reconstruction fails closed via {@see ReviewSession::resume} (StaleReviewSessionException).
 */
final class ReviewWorkspaceInteractor
{
    public function __construct(
        private readonly ReviewInputProviderInterface $provider,
        private readonly PreviewProjectionInterface $preview,
        private readonly ReviewSessionStoreInterface $store,
        private readonly ReviewApprovalService $approval,
    ) {}

    /**
     * Open a fresh review for a target and persist its snapshot. Returns the reconstructible session so the
     * caller can render it immediately.
     */
    public function open(ReviewSessionId $id, PageTranslationKey $target): ReviewSession
    {
        $subject = $this->provider->provide($target);
        $session = ReviewSession::open($id, $subject, $this->preview, $this->approval);
        $this->store->put($session->snapshot());

        return $session;
    }

    /**
     * Reconstruct the current session (read-only) for rendering. Throws {@see
     * \Plugins\PageBuilder\LayoutTranslation\Review\Session\StaleReviewSessionException} if the plan drifted.
     */
    public function session(ReviewSessionId $id, PageTranslationKey $target): ReviewSession
    {
        return $this->rehydrate($id, $target);
    }

    public function decide(ReviewSessionId $id, PageTranslationKey $target, string $origin, SynchronizationDecision $decision): ReviewSession
    {
        $session = $this->rehydrate($id, $target);
        $session->decide($origin, $decision);
        $this->store->put($session->snapshot());

        return $session;
    }

    public function resetDecision(ReviewSessionId $id, PageTranslationKey $target, string $origin): ReviewSession
    {
        $session = $this->rehydrate($id, $target);
        $session->resetDecision($origin);
        $this->store->put($session->snapshot());

        return $session;
    }

    public function select(ReviewSessionId $id, PageTranslationKey $target, string $origin): ReviewSession
    {
        $session = $this->rehydrate($id, $target);
        $session->select($origin);
        $this->store->put($session->snapshot());

        return $session;
    }

    public function selectNextUnreviewed(ReviewSessionId $id, PageTranslationKey $target): ReviewSession
    {
        $session = $this->rehydrate($id, $target);
        $session->selectNextUnreviewed();
        $this->store->put($session->snapshot());

        return $session;
    }

    /**
     * Freeze decisions and hand off to the frozen coordinator exactly once (via the runtime's approval
     * service). Returns the typed pipeline result verbatim for presentation.
     */
    public function submit(ReviewSessionId $id, PageTranslationKey $target, ?string $actor): SynchronizationExecutionResult
    {
        $session = $this->rehydrate($id, $target);
        $result = $session->submit($actor);
        $this->store->forget($id);

        return $result;
    }

    public function abandon(ReviewSessionId $id): void
    {
        $this->store->forget($id);
    }

    private function rehydrate(ReviewSessionId $id, PageTranslationKey $target): ReviewSession
    {
        $snapshot = $this->store->get($id);
        if ($snapshot === null) {
            throw new InvalidArgumentException("No review session for id: {$id->value}");
        }

        $subject = $this->provider->provide($target);

        // Fail-closed on provenance drift (LT-8A §8.2). Selection/target parity is a cheap sanity check.
        if ($snapshot->targetKey !== $target->value()) {
            throw new InvalidArgumentException('Review session target does not match the requested target.');
        }

        return ReviewSession::resume($snapshot, $subject, $this->preview, $this->approval);
    }
}
