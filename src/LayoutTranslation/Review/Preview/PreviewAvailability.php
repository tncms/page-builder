<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Review\Preview;

/**
 * Whether a preview fragment could be rendered for an origin (LT-8B, per
 * LT-8A-REVIEW-WORKSPACE-ARCHITECTURE §6).
 *
 * Layer: Translation (review preview). Fail-soft: a missing side is reported, never fabricated and never
 * repaired. Preview availability changes no plan and no decision default.
 */
enum PreviewAvailability: string
{
    case Available = 'available';

    case SourceGone = 'source_gone';

    case TargetGone = 'target_gone';

    case NotBound = 'not_bound';
}
