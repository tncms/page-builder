<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Recovery;

/**
 * The motivation for attempting a recovery (LT-9D, per LT-9A recovery workflow).
 *
 * Layer: Translation (recovery). The typed reason a user is recovering from a prior synchronization
 * outcome — the WHY that a {@see RecoveryRequest} carries and a {@see RecoveryPlan} echoes. It is distinct
 * from {@see RecoveryIneligibilityReason} (why recovery is refused) and from {@see RecoveryConflictType}
 * (how current reality diverges); a reason never implies eligibility.
 */
enum RecoveryReason: string
{
    case ReplayDiverged = 'replay_diverged';

    case ReplayUnavailable = 'replay_unavailable';

    case ExecutionFailed = 'execution_failed';

    case BrokenChain = 'broken_chain';

    case MissingBaseline = 'missing_baseline';

    case SupersededReconcile = 'superseded_reconcile';

    case ManualIntervention = 'manual_intervention';

    case PolicyReview = 'policy_review';
}
