<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Recovery;

/**
 * The typed category of a recovery conflict (LT-9D).
 *
 * Layer: Translation (recovery / conflict). A conflict is always EXPLICIT — nothing is inferred. Blocking
 * conflicts refuse recovery outright; non-blocking (soft) conflicts require an explicit override. The
 * distinction is fixed here so the {@see RecoveryDecision} derivation stays deterministic.
 */
enum RecoveryConflictType: string
{
    case CurrentStateDiffers = 'current_state_differs';

    case BaselineDiffers = 'baseline_differs';

    case DocumentEvolved = 'document_evolved';

    case HistorySuperseded = 'history_superseded';

    case SnapshotUnavailable = 'snapshot_unavailable';

    case PolicyConflict = 'policy_conflict';

    case PermissionConflict = 'permission_conflict';

    /** Blocking conflicts refuse recovery; soft conflicts merely require an override. */
    public function isBlocking(): bool
    {
        return match ($this) {
            self::HistorySuperseded,
            self::SnapshotUnavailable,
            self::PolicyConflict,
            self::PermissionConflict => true,
            self::CurrentStateDiffers,
            self::BaselineDiffers,
            self::DocumentEvolved => false,
        };
    }
}
