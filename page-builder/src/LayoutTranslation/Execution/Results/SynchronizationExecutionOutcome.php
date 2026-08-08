<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Execution\Results;

/**
 * The typed outcome of a synchronization execution (LT-7B1, per LT-7-FAILURE-MODEL).
 *
 * Layer: Translation (execution contract). The single closed set every execution result carries. Raw
 * exceptions, SQL, models and rows are never surfaced — they are normalized to one of these. Two are
 * success outcomes (`Executed`, `NoOp`); the rest are fail-closed. Only `Executed` mutates the target.
 */
enum SynchronizationExecutionOutcome: string
{
    /** Approved operations applied; baseline advanced; one history row appended — atomically. */
    case Executed = 'executed';

    /** Empty plan, or every item kept/ignored — nothing to do. Success, no write. */
    case NoOp = 'no_op';

    case FeatureDisabled = 'feature_disabled';

    case CapabilityUnsupported = 'capability_unsupported';

    case TargetNotBound = 'target_not_bound';

    case BaselineMissing = 'baseline_missing';

    case SourceUnavailable = 'source_unavailable';

    case TargetUnavailable = 'target_unavailable';

    /** Current B/S/T fingerprints ≠ the plan's provenance — the plan no longer describes reality. */
    case PlanStale = 'plan_stale';

    case AcceptanceInvalid = 'acceptance_invalid';

    case CorrespondenceFault = 'correspondence_fault';

    case DocumentInvalid = 'document_invalid';

    case ConcurrentConflict = 'concurrent_conflict';

    case IntegrityFault = 'integrity_fault';

    case PersistenceFailure = 'persistence_failure';

    public function isSuccess(): bool
    {
        return $this === self::Executed || $this === self::NoOp;
    }

    public function isFailure(): bool
    {
        return ! $this->isSuccess();
    }

    /** Only a fully executed sync mutates the target. */
    public function didMutate(): bool
    {
        return $this === self::Executed;
    }
}
