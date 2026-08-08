<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Review\Tree;

/**
 * The read-only review tree — a pure projection of (plan, decisions) (LT-8B, per
 * LT-8A-REVIEW-WORKSPACE-ARCHITECTURE §3 and INV-LT-8A-07/08).
 *
 * Layer: Translation (review projection / value). QUERY side only: it exposes groups, a flat
 * review-priority ordering for navigation, and origin lookup — never a mutator. It holds no authoritative
 * state; it is rebuilt on demand from the plan and the DecisionSet and may be discarded at any time
 * (INV-LT-8A-07). Payload-safe: it carries no content (content is fetched lazily and only by preview).
 */
final class ReviewTree
{
    /**
     * @param  array<int, ReviewGroup>  $groups
     * @param  array<int, ReviewNode>   $ordered
     * @param  array<string, ReviewNode>  $index
     */
    private function __construct(
        private readonly array $groups,
        private readonly array $ordered,
        private readonly array $index,
    ) {}

    /**
     * @param  array<int, ReviewGroup>  $groups  in review-priority order
     */
    public static function of(array $groups): self
    {
        $ordered = [];
        $index = [];

        foreach ($groups as $group) {
            foreach ($group->nodes as $node) {
                $ordered[] = $node;
                $index[$node->origin] = $node;
            }
        }

        return new self(array_values($groups), $ordered, $index);
    }

    /**
     * @return array<int, ReviewGroup>
     */
    public function groups(): array
    {
        return $this->groups;
    }

    /**
     * Flat, review-priority node ordering (Conflict → ReviewRequired → Safe, plan order within each).
     *
     * @return array<int, ReviewNode>
     */
    public function nodes(): array
    {
        return $this->ordered;
    }

    /**
     * @return array<int, string>
     */
    public function origins(): array
    {
        return array_map(static fn (ReviewNode $n): string => $n->origin, $this->ordered);
    }

    public function nodeFor(string $origin): ?ReviewNode
    {
        return $this->index[$origin] ?? null;
    }

    public function has(string $origin): bool
    {
        return isset($this->index[$origin]);
    }

    public function count(): int
    {
        return count($this->ordered);
    }

    public function isEmpty(): bool
    {
        return $this->ordered === [];
    }

    /**
     * Non-safe nodes the reviewer has not yet explicitly decided (drives the fail-closed submit gate).
     */
    public function undecidedReviewCount(): int
    {
        $count = 0;
        foreach ($this->ordered as $node) {
            if ($node->needsReview() && ! $node->decided) {
                $count++;
            }
        }

        return $count;
    }
}
