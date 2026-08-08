<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Review\Session;

use RuntimeException;

/**
 * A resume was attempted against drifted provenance (LT-8B, per LT-8A-REVIEW-WORKSPACE-ARCHITECTURE
 * §8.2 and INV-LT-8A-04/07).
 *
 * Layer: Translation (review session). On resume the retained provenance no longer matches the
 * re-derived subject's provenance — the working decisions were made against a plan that no longer
 * describes reality and are discarded fail-closed. The reviewer must re-review the fresh plan.
 */
final class StaleReviewSessionException extends RuntimeException
{
    public static function driftedProvenance(): self
    {
        return new self('The review session is stale: source, target, or baseline drifted since it was opened. Re-review the current plan.');
    }
}
