<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Recovery;

use Plugins\PageBuilder\LayoutTranslation\Execution\Contracts\ExecutionEligibilityInterface;
use Plugins\PageBuilder\LayoutTranslation\Recovery\Contracts\RecoverySourceGatewayInterface;
use Plugins\PageBuilder\LayoutTranslation\Timeline\Entry\TimelineEntry;
use Plugins\PageBuilder\LayoutTranslation\Timeline\Entry\TimelineEntryId;
use Plugins\PageBuilder\LayoutTranslation\Timeline\Resolution\TimelineDocumentResolver;
use Plugins\PageBuilder\LayoutTranslation\TimelineInfrastructure\TimelineApplicationService;

/**
 * The default recovery read seam over the frozen runtime APIs (LT-9D).
 *
 * Layer: Translation (recovery / source). It assembles an immutable {@see RecoveryCandidate} by observing —
 * never mutating — the frozen Timeline, Replay, Snapshot Resolver and eligibility runtimes. It reads no
 * storage directly: history and snapshots come through the runtime facades, feature/capability through the
 * execution eligibility gate. Current-state fingerprints (current baseline/source) are left undeterminable
 * (null) here — supplying them is a later state-probe wiring step, and the frozen Coordinator re-verifies
 * provenance under lock regardless, so no conflict is fabricated from their absence.
 */
final class DefaultRecoverySourceGateway implements RecoverySourceGatewayInterface
{
    public function __construct(
        private readonly TimelineApplicationService $timeline,
        private readonly TimelineDocumentResolver $documents,
        private readonly ExecutionEligibilityInterface $eligibility,
    ) {}

    public function candidate(RecoveryRequest $request): RecoveryCandidate
    {
        $timeline = $this->timeline->timeline($request->target);
        $entry = $timeline->entryFor($request->entryId);

        if ($entry === null) {
            return RecoveryCandidate::notFound($request->entryId, $request->target->value());
        }

        $replay = $this->timeline->replay($request->target, $request->entryId);

        return new RecoveryCandidate(
            $entry->id,
            $entry->pageTranslationKey,
            true,
            $entry->actor,
            $entry->executedAt,
            $entry->planFingerprint,
            $entry->baselineBefore,
            $entry->baselineAfter,
            $entry->sourceLayoutId,
            $entry->appliedCount,
            $entry->skippedCount,
            $this->eligibility->featureEnabled(),
            $this->eligibility->supportsSync($request->target),
            true,
            $replay->status,
            $this->documents->baselineBefore($entry) !== null,
            $this->documents->source($entry) !== null,
            $this->documents->targetBefore($entry) !== null,
            $this->documents->targetAfter($entry) !== null,
            $timeline->isContinuous(),
            $this->supersededBy($timeline->entries(), $entry),
            null,
            null,
        );
    }

    /**
     * The chain tail id when this entry is not the tail, else null (the entry is still current).
     *
     * @param  array<int, TimelineEntry>  $entries  ordered by (executed_at, id)
     */
    private function supersededBy(array $entries, TimelineEntry $entry): ?TimelineEntryId
    {
        if ($entries === []) {
            return null;
        }

        $last = $entries[count($entries) - 1];

        return $last->id->equals($entry->id) ? null : $last->id;
    }
}
