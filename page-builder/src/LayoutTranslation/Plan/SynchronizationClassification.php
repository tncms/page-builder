<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Plan;

/**
 * How a source change is classified for synchronization (LT-6, per LT-0-ADR-001 D8/D9).
 *
 * Layer: Translation (plan). `Unsupported` is reserved for change kinds a future
 * phase cannot safely plan.
 */
enum SynchronizationClassification: string
{
    /** Safe to apply — the target has not customized this node. */
    case Safe = 'safe';

    /** Requires translation review before applying (e.g. source text changed after translation). */
    case ReviewRequired = 'review_required';

    /** Source and target changed the same node incompatibly. */
    case Conflict = 'conflict';

    /** Reserved: a change kind that cannot be safely planned yet. */
    case Unsupported = 'unsupported';
}
