<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Recovery;

/**
 * The strategy a recovery uses to recover from a prior synchronization outcome (LT-9D, per INV-LT-9D-04).
 *
 * Layer: Translation (recovery). A recovery mode is a STRATEGY, never a subclass of the timeline. Modes
 * differ only in HOW recovery reaches a safe state:
 *
 *  - {@see ReplayOnly}  — observational recovery; delegates to the frozen Replay Runtime and never executes.
 *  - {@see ReExecute}   — creates a NEW synchronization execution through the frozen Coordinator.
 *  - {@see Retry}       — re-attempts a failed execution as a NEW execution through the frozen Coordinator.
 *  - {@see Manual}      — human-driven recovery; no automated execution is performed.
 *  - {@see AiAssisted}  — RESERVED for a later phase; not executable here (INV-LT-9D-03).
 *
 * Only executing modes reach the Coordinator; the Coordinator remains the sole execution authority.
 */
enum RecoveryMode: string
{
    case ReplayOnly = 'replay_only';

    case ReExecute = 're_execute';

    case Retry = 'retry';

    case Manual = 'manual';

    case AiAssisted = 'ai_assisted';

    /** Executing modes create a NEW synchronization execution via the frozen Coordinator. */
    public function isExecuting(): bool
    {
        return $this === self::ReExecute || $this === self::Retry;
    }

    /** Observational recovery — delegates to the read-only Replay Runtime, never executes. */
    public function isObservationalOnly(): bool
    {
        return $this === self::ReplayOnly;
    }

    public function isManual(): bool
    {
        return $this === self::Manual;
    }

    /** Reserved strategies are not executable in this phase. */
    public function isReserved(): bool
    {
        return $this === self::AiAssisted;
    }

    /** The additional permission an executing mode requires; null for non-executing modes. */
    public function executePermission(): ?string
    {
        return $this->isExecuting() ? 'timeline.recovery.execute' : null;
    }
}
