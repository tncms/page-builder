<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Baseline\Results;

/**
 * The typed outcome of a baseline capture (LT-4).
 *
 * Layer: Translation (baseline / result). Typed outcomes, never magic strings.
 */
enum BaselineCaptureOutcome: string
{
    case Captured = 'captured';

    case FeatureDisabled = 'feature_disabled';

    case TargetNotBound = 'target_not_bound';

    case SourceUnavailable = 'source_unavailable';

    case IntegrityFault = 'integrity_fault';

    case PersistenceFailure = 'persistence_failure';
}
