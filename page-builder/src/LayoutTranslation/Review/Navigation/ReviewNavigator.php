<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Review\Navigation;

use Plugins\PageBuilder\LayoutTranslation\Plan\SynchronizationClassification;
use Plugins\PageBuilder\LayoutTranslation\Review\Tree\ReviewTree;

/**
 * Stateless navigation over a projected review tree (LT-8B, per
 * LT-8A-REVIEW-WORKSPACE-ARCHITECTURE §3).
 *
 * Layer: Translation (review navigation / query). A pure function of the {@see ReviewTree}: it computes
 * the next/previous origin and the next item that still needs review, following the tree's deterministic
 * review-priority ordering. It holds no state (INV-LT-8A-07) and mutates nothing (INV-LT-8A-08); it
 * returns origins, never nodes it could mutate.
 */
final class ReviewNavigator
{
    public function first(ReviewTree $tree): ?string
    {
        return $tree->origins()[0] ?? null;
    }

    public function last(ReviewTree $tree): ?string
    {
        $origins = $tree->origins();

        return $origins === [] ? null : $origins[count($origins) - 1];
    }

    public function next(ReviewTree $tree, string $origin): ?string
    {
        $origins = $tree->origins();
        $i = array_search($origin, $origins, true);

        if ($i === false) {
            return null;
        }

        return $origins[$i + 1] ?? null;
    }

    public function previous(ReviewTree $tree, string $origin): ?string
    {
        $origins = $tree->origins();
        $i = array_search($origin, $origins, true);

        if ($i === false || $i === 0) {
            return null;
        }

        return $origins[$i - 1] ?? null;
    }

    /**
     * The first non-safe node the reviewer has not yet explicitly decided.
     */
    public function firstUnreviewed(ReviewTree $tree): ?string
    {
        foreach ($tree->nodes() as $node) {
            if ($node->needsReview() && ! $node->decided) {
                return $node->origin;
            }
        }

        return null;
    }

    /**
     * The next non-safe, undecided node strictly after the given origin (wrapping is not implied).
     */
    public function nextUnreviewed(ReviewTree $tree, string $origin): ?string
    {
        $origins = $tree->origins();
        $i = array_search($origin, $origins, true);

        if ($i === false) {
            return null;
        }

        for ($j = $i + 1; $j < count($origins); $j++) {
            $node = $tree->nodeFor($origins[$j]);
            if ($node !== null && $node->needsReview() && ! $node->decided) {
                return $node->origin;
            }
        }

        return null;
    }

    public function firstConflict(ReviewTree $tree): ?string
    {
        foreach ($tree->nodes() as $node) {
            if ($node->classification === SynchronizationClassification::Conflict) {
                return $node->origin;
            }
        }

        return null;
    }
}
