<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Review\Contracts;

use Plugins\PageBuilder\LayoutTranslation\Identity\PageTranslationKey;
use Plugins\PageBuilder\LayoutTranslation\Review\Subject\ReviewSubject;

/**
 * Provides the immutable {@see ReviewSubject} a review binds to (LT-8B, per
 * LT-8A-REVIEW-WORKSPACE-ARCHITECTURE §2.1/§9).
 *
 * Layer: Translation (review boundary). The concrete implementation DELEGATES to the frozen LT-5 diff and
 * LT-6 planner and captures the {@see \Plugins\PageBuilder\LayoutTranslation\Execution\Provenance\PlanProvenance}
 * of the exact B/S/T it planned from. It computes no new classification and re-plans nothing
 * (INV-LT-8A-01). Re-invoking it for the same target re-derives an identical plan (LT-6 determinism),
 * which is what makes a disposable session reconstructible (INV-LT-8A-07). The concrete adapter is
 * infrastructure wiring (a later phase); this contract is the seam the runtime consumes.
 */
interface ReviewInputProviderInterface
{
    public function provide(PageTranslationKey $target): ReviewSubject;
}
