<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Execution\Adapters;

use Plugins\PageBuilder\Availability\UnavailableSubtreeGuard;
use Plugins\PageBuilder\Availability\UnavailableSubtreeViolation;
use Plugins\PageBuilder\LayoutTranslation\Execution\Contracts\TargetDraftWriterInterface;
use Plugins\PageBuilder\LayoutTranslation\Execution\Results\SynchronizationExecutionOutcome;
use Plugins\PageBuilder\LayoutTranslation\Execution\Services\ExecutionConflictException;
use Plugins\PageBuilder\Support\LayoutNormalizer;
use Plugins\PageBuilder\Support\LayoutStore;

/**
 * Production target draft writer (LT-7C).
 *
 * Layer: Translation (execution adapter). A thin bridge to the canonical `LayoutStore::saveDraft`
 * mutation path — the same path clone (LT-3) and baseline capture (LT-4) use. It writes the T-derived
 * document produced by the mutation engine and participates in the coordinator's transaction; it never
 * begins or commits it.
 */
final class LayoutStoreTargetDraftWriter implements TargetDraftWriterInterface
{
    public function __construct(
        private readonly LayoutStore $layouts,
        private readonly UnavailableSubtreeGuard $unavailable,
        private readonly LayoutNormalizer $normalizer,
    ) {}

    public function saveDraft(string $ownerType, int|string $ownerId, array $payload): void
    {
        // PB-XREG-4-R1 — a sync never changes, removes, moves or adds content whose provider is
        // unavailable in the target; it aborts (rolled back by the coordinator) instead.
        $current = $this->layouts->getDraftPayload($ownerType, $ownerId);
        try {
            $this->unavailable->assertPreserved($current, $this->normalizer->normalize($payload), freezeUnknown: true);
        } catch (UnavailableSubtreeViolation $e) {
            throw new ExecutionConflictException(SynchronizationExecutionOutcome::DocumentInvalid, 'unavailable_'.$e->reason);
        }

        $this->layouts->saveDraft($ownerType, $ownerId, $this->unavailable->restore($payload, $current, freezeUnknown: true));
    }
}
