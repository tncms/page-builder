<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Recovery;

use Plugins\PageBuilder\LayoutTranslation\Timeline\Entry\TimelineEntryId;
use Plugins\PageBuilder\LayoutTranslation\Timeline\Replay\ReplayStatus;

/**
 * The immutable, payload-safe observation snapshot of one recoverable execution (LT-9D).
 *
 * Layer: Translation (recovery / candidate). Every field is assembled by the
 * {@see Contracts\RecoverySourceGatewayInterface} from the frozen runtime APIs ONLY — Timeline, Replay,
 * Explainability, the Snapshot Resolver and Synchronization History — never from storage directly. It carries
 * fingerprint references, availability flags and counts, but no document content. Because all downstream
 * eligibility and conflict analysis reads only this immutable candidate, the recovery decision is
 * deterministic (INV-LT-9D-05): identical observations always yield the identical plan.
 */
final class RecoveryCandidate
{
    public function __construct(
        public readonly TimelineEntryId $entryId,
        public readonly string $pageTranslationKey,
        public readonly bool $entryExists,
        public readonly ?string $actor,
        public readonly string $executedAt,
        public readonly string $planFingerprint,
        public readonly string $baselineBeforeRef,
        public readonly string $baselineAfterRef,
        public readonly int $sourceLayoutId,
        public readonly int $appliedCount,
        public readonly int $skippedCount,
        public readonly bool $featureEnabled,
        public readonly bool $capabilitySupported,
        public readonly bool $versionSupported,
        public readonly ReplayStatus $replayStatus,
        public readonly bool $baselineBeforeAvailable,
        public readonly bool $sourceSnapshotAvailable,
        public readonly bool $targetBeforeAvailable,
        public readonly bool $targetAfterAvailable,
        public readonly bool $chainContinuous,
        public readonly ?TimelineEntryId $supersededBy,
        public readonly ?string $currentBaselineRef,
        public readonly ?string $currentSourceRef,
    ) {}

    /**
     * The honest "no such entry" candidate — every downstream check treats this as ineligible.
     */
    public static function notFound(TimelineEntryId $entryId, string $pageTranslationKey): self
    {
        return new self(
            $entryId,
            $pageTranslationKey,
            false,
            null,
            '',
            '',
            '',
            '',
            0,
            0,
            0,
            false,
            false,
            false,
            ReplayStatus::Unavailable,
            false,
            false,
            false,
            false,
            false,
            null,
            null,
            null,
        );
    }

    /** The recovered entry is still the chain tail (nothing superseded it). */
    public function isTail(): bool
    {
        return $this->supersededBy === null;
    }

    public function replayReproduced(): bool
    {
        return $this->replayStatus === ReplayStatus::Reproduced;
    }

    public function replayDiverged(): bool
    {
        return $this->replayStatus === ReplayStatus::Diverged;
    }

    public function replayUnavailable(): bool
    {
        return $this->replayStatus === ReplayStatus::Unavailable;
    }

    /** All snapshots needed to re-derive the execution are retained. */
    public function hasReExecutionSnapshots(): bool
    {
        return $this->baselineBeforeAvailable && $this->sourceSnapshotAvailable;
    }

    public function hasBaseline(): bool
    {
        return $this->baselineBeforeRef !== '';
    }

    /** Whether the current bound state is known (null current reads mean "undeterminable", not "differs"). */
    public function currentStateKnown(): bool
    {
        return $this->currentBaselineRef !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'entry_id' => $this->entryId->value,
            'page_translation_key' => $this->pageTranslationKey,
            'entry_exists' => $this->entryExists,
            'actor' => $this->actor,
            'executed_at' => $this->executedAt,
            'plan_fingerprint' => $this->planFingerprint,
            'baseline_before_ref' => $this->baselineBeforeRef,
            'baseline_after_ref' => $this->baselineAfterRef,
            'source_layout_id' => $this->sourceLayoutId,
            'applied_count' => $this->appliedCount,
            'skipped_count' => $this->skippedCount,
            'feature_enabled' => $this->featureEnabled,
            'capability_supported' => $this->capabilitySupported,
            'version_supported' => $this->versionSupported,
            'replay_status' => $this->replayStatus->value,
            'baseline_before_available' => $this->baselineBeforeAvailable,
            'source_snapshot_available' => $this->sourceSnapshotAvailable,
            'target_before_available' => $this->targetBeforeAvailable,
            'target_after_available' => $this->targetAfterAvailable,
            'chain_continuous' => $this->chainContinuous,
            'superseded_by' => $this->supersededBy?->value,
            'current_baseline_ref' => $this->currentBaselineRef,
            'current_source_ref' => $this->currentSourceRef,
        ];
    }
}
