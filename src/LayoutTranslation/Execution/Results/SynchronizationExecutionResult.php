<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Execution\Results;

use Plugins\PageBuilder\LayoutTranslation\Execution\Provenance\ProvenanceDrift;

/**
 * The immutable, typed result of a synchronization execution (LT-7B1, per LT-7-EXECUTION-ARCHITECTURE
 * §9).
 *
 * Layer: Translation (execution result). Payload-safe — the typed outcome, counts, the new baseline
 * fingerprint (on success), the history id (on success), and an optional non-sensitive detail. It never
 * exposes node content, Eloquent models, raw exceptions, SQL, query builders or rows. Constructed only
 * through named constructors; the constructor is private.
 */
final class SynchronizationExecutionResult
{
    private function __construct(
        public readonly SynchronizationExecutionOutcome $outcome,
        public readonly int $appliedCount,
        public readonly int $skippedCount,
        public readonly ?string $newBaselineFingerprint,
        public readonly ?int $historyId,
        public readonly ?string $detail,
    ) {}

    public static function executed(int $appliedCount, int $skippedCount, string $newBaselineFingerprint, ?int $historyId = null): self
    {
        return new self(SynchronizationExecutionOutcome::Executed, $appliedCount, $skippedCount, $newBaselineFingerprint, $historyId, null);
    }

    public static function noop(): self
    {
        return new self(SynchronizationExecutionOutcome::NoOp, 0, 0, null, null, null);
    }

    public static function stale(?ProvenanceDrift $drift = null): self
    {
        return new self(SynchronizationExecutionOutcome::PlanStale, 0, 0, null, null, $drift?->value);
    }

    public static function ineligible(SynchronizationExecutionOutcome $outcome, ?string $detail = null): self
    {
        return new self($outcome, 0, 0, null, null, $detail);
    }

    public static function failure(SynchronizationExecutionOutcome $outcome, ?string $detail = null): self
    {
        return new self($outcome, 0, 0, null, null, $detail);
    }

    public function isSuccessful(): bool
    {
        return $this->outcome->isSuccess();
    }

    public function didMutate(): bool
    {
        return $this->outcome->didMutate();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'outcome' => $this->outcome->value,
            'applied_count' => $this->appliedCount,
            'skipped_count' => $this->skippedCount,
            'new_baseline_fingerprint' => $this->newBaselineFingerprint,
            'history_id' => $this->historyId,
            'detail' => $this->detail,
        ];
    }
}
