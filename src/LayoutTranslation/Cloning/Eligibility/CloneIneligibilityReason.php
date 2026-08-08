<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Cloning\Eligibility;

/**
 * The typed reason a clone is not eligible (LT-3).
 *
 * Layer: Translation (eligibility). Eligibility is a distinct layer from
 * Capability, Authorization and Workflow. Every rejection carries one of these
 * reasons — never a bare boolean.
 */
enum CloneIneligibilityReason: string
{
    case FeatureDisabled = 'feature_disabled';

    case CapabilityUnsupported = 'capability_unsupported';

    case SameTranslation = 'same_translation';

    case DifferentTranslationGroup = 'different_translation_group';

    case TargetNotFound = 'target_not_found';

    case SourceNotFound = 'source_not_found';

    case SourcePayloadUnavailable = 'source_payload_unavailable';

    case TargetAlreadyBound = 'target_already_bound';

    case TargetLayoutConflict = 'target_layout_conflict';

    case InvalidIdentity = 'invalid_identity';

    case IntegrityFault = 'integrity_fault';
}
