<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Review\Subject;

use Plugins\PageBuilder\LayoutTranslation\Execution\Provenance\PlanProvenance;
use Plugins\PageBuilder\LayoutTranslation\Identity\PageTranslationKey;
use Plugins\PageBuilder\LayoutTranslation\Plan\SynchronizationPlan;

/**
 * The immutable input a review is bound to (LT-8B, per LT-8A-REVIEW-WORKSPACE-ARCHITECTURE §2.1).
 *
 * Layer: Translation (review / value). Payload-safe: a target identity, the frozen LT-6 plan verbatim,
 * and the LT-7 provenance the plan was built from. It is produced by the {@see
 * \Plugins\PageBuilder\LayoutTranslation\Review\Contracts\ReviewInputProviderInterface} (which delegates
 * to the frozen LT-5 diff + LT-6 planner and captures provenance) — the Review layer never re-plans and
 * never re-diffs (INV-LT-8A-01). The session treats this as read-only truth (INV-LT-8A-07).
 */
final class ReviewSubject
{
    public function __construct(
        public readonly PageTranslationKey $target,
        public readonly SynchronizationPlan $plan,
        public readonly PlanProvenance $provenance,
    ) {}
}
