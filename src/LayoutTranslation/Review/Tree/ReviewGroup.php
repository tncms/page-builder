<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Review\Tree;

use Plugins\PageBuilder\LayoutTranslation\Plan\SynchronizationClassification;

/**
 * A read-only group of review nodes sharing a classification (LT-8B, per
 * LT-8A-REVIEW-WORKSPACE-ARCHITECTURE §3.1 and INV-LT-8A-08).
 *
 * Layer: Translation (review projection / value). QUERY side only — no mutator. Groups exist to surface
 * review priority (Conflict → ReviewRequired → Safe); node order within a group follows the deterministic
 * plan order.
 */
final class ReviewGroup
{
    /**
     * @param  array<int, ReviewNode>  $nodes
     */
    public function __construct(
        public readonly SynchronizationClassification $classification,
        public readonly array $nodes,
    ) {}

    public function count(): int
    {
        return count($this->nodes);
    }

    public function isEmpty(): bool
    {
        return $this->nodes === [];
    }
}
