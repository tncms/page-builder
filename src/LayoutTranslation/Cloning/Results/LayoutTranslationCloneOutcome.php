<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Cloning\Results;

/**
 * The typed outcome of a clone attempt (LT-3).
 *
 * Layer: Translation (clone result). Typed outcomes, never magic strings; safe
 * domain information only — never raw exceptions, SQL, models or rows.
 */
enum LayoutTranslationCloneOutcome: string
{
    case Created = 'created';

    case AlreadyCloned = 'already_cloned';

    case FeatureDisabled = 'feature_disabled';

    case CapabilityUnsupported = 'capability_unsupported';

    case SourceNotFound = 'source_not_found';

    case SourcePayloadUnavailable = 'source_payload_unavailable';

    case TargetNotFound = 'target_not_found';

    case DifferentTranslationGroup = 'different_translation_group';

    case SameTranslation = 'same_translation';

    case TargetLayoutConflict = 'target_layout_conflict';

    case InvalidIdentity = 'invalid_identity';

    case IntegrityFault = 'integrity_fault';

    case ConcurrentConflict = 'concurrent_conflict';

    case PersistenceFailure = 'persistence_failure';
}
