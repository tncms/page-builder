<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Recovery;

/**
 * The pure, deterministic recovery conflict analyzer (LT-9D).
 *
 * Layer: Translation (recovery / conflict). It detects, from the immutable {@see RecoveryCandidate} plus the
 * {@see RecoveryPolicy} and {@see RecoveryRequest}, every way current reality diverges from what a recovery
 * assumes. Every conflict is EXPLICIT — nothing is inferred: an unknown current-state read (null) is treated
 * as "undeterminable", never as "differs", because the frozen Coordinator re-verifies provenance under lock
 * before any mutation. It reads no storage and never executes; identical inputs produce the identical
 * conflict list.
 */
final class RecoveryConflictResolver
{
    /**
     * @return array<int, RecoveryConflict>
     */
    public function resolve(RecoveryCandidate $candidate, RecoveryPolicy $policy, RecoveryRequest $request): array
    {
        if (! $candidate->entryExists) {
            return [];
        }

        $conflicts = [];

        // History has moved past this entry — recovering from it is superseded.
        if (! $candidate->isTail()) {
            $conflicts[] = RecoveryConflict::of(
                RecoveryConflictType::HistorySuperseded,
                'superseded_by_entry_'.($candidate->supersededBy?->value ?? 'unknown'),
            );
        }

        // Required snapshots were not retained (fail-soft signal, not fabricated content).
        if (! $candidate->hasReExecutionSnapshots()) {
            $conflicts[] = RecoveryConflict::of(
                RecoveryConflictType::SnapshotUnavailable,
                'baseline_or_source_snapshot_missing',
            );
        }

        // The recorded outcome no longer reproduces — a tamper/corruption signal from Replay.
        if ($candidate->replayDiverged()) {
            $conflicts[] = RecoveryConflict::of(
                RecoveryConflictType::CurrentStateDiffers,
                'recorded_outcome_no_longer_reproduces',
            );
        }

        // The current bound baseline differs from the one this entry advanced to (KNOWN divergence only).
        if ($candidate->currentBaselineRef !== null && $candidate->currentBaselineRef !== $candidate->baselineAfterRef) {
            $conflicts[] = RecoveryConflict::of(RecoveryConflictType::BaselineDiffers, 'current_baseline_advanced');
        }

        // The current source content-addresses to something other than what was synced (KNOWN divergence only).
        if ($candidate->currentSourceRef !== null && $candidate->currentSourceRef !== $candidate->baselineAfterRef) {
            $conflicts[] = RecoveryConflict::of(RecoveryConflictType::DocumentEvolved, 'source_document_evolved');
        }

        // Policy no longer permits the requested strategy.
        if (! $policy->allows($request->mode)) {
            $conflicts[] = RecoveryConflict::of(RecoveryConflictType::PolicyConflict, 'mode_'.$request->mode->value.'_not_allowed');
        }

        // The actor lacks a permission the requested strategy requires.
        if ($request->mode->executePermission() !== null && ! $request->grants($request->mode->executePermission())) {
            $conflicts[] = RecoveryConflict::of(RecoveryConflictType::PermissionConflict, 'missing_'.$request->mode->executePermission());
        }

        return $conflicts;
    }
}
