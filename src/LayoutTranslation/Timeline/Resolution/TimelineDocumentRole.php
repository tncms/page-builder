<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Timeline\Resolution;

/**
 * The role a resolved timeline document plays for an entry (LT-9B, per LT-9A §7).
 *
 * Layer: Translation (timeline / resolution). `BaselineBefore` and `Source` (= `BaselineAfter`) are
 * content-addressed and resolved by fingerprint **reference**; `TargetBefore`/`TargetAfter` are resolved by
 * entry identity (history id). The distinction between a *reference* and a stored *blob* is preserved by the
 * snapshot source, never by the timeline (INV-LT-9-TL-05).
 */
enum TimelineDocumentRole: string
{
    case BaselineBefore = 'baseline_before';

    case BaselineAfter = 'baseline_after';

    case Source = 'source';

    case TargetBefore = 'target_before';

    case TargetAfter = 'target_after';
}
