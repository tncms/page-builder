<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Recovery;

/**
 * The typed reason a recovery is not eligible (LT-9D).
 *
 * Layer: Translation (recovery / eligibility). Eligibility is a distinct layer from Conflict analysis,
 * Policy and Permission. Every rejection carries one or more of these reasons — never a bare boolean.
 * Eligibility is deterministic: identical candidate + policy + request produce identical reasons.
 */
enum RecoveryIneligibilityReason: string
{
    case FeatureDisabled = 'feature_disabled';

    case CapabilityUnsupported = 'capability_unsupported';

    case EntryNotFound = 'entry_not_found';

    case ReplayUnavailable = 'replay_unavailable';

    case MissingSnapshot = 'missing_snapshot';

    case BrokenChain = 'broken_chain';

    case MissingBaseline = 'missing_baseline';

    case UnsupportedVersion = 'unsupported_version';

    case AlreadySuperseded = 'already_superseded';

    case PermissionDenied = 'permission_denied';

    case PolicyDenied = 'policy_denied';

    case ModeReserved = 'mode_reserved';

    case ModeInfeasible = 'mode_infeasible';
}
