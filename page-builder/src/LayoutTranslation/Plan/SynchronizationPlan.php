<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Plan;

/**
 * An immutable, reviewable synchronization plan (LT-6, per LT-0-ADR-001 D10).
 *
 * Layer: Translation (plan / value). A preview of what a synchronization WOULD do,
 * classified safe / review / conflict with fail-closed default decisions. It is
 * read-only and never mutates the target — execution is LT-7.
 */
final class SynchronizationPlan
{
    /**
     * @param  array<int, SynchronizationPlanItem>  $items
     */
    public function __construct(private readonly array $items) {}

    /**
     * @return array<int, SynchronizationPlanItem>
     */
    public function items(): array
    {
        return $this->items;
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    /**
     * @return array<int, SynchronizationPlanItem>
     */
    public function safeItems(): array
    {
        return array_values(array_filter($this->items, static fn (SynchronizationPlanItem $i): bool => $i->isSafe()));
    }

    public function requiresReview(): bool
    {
        foreach ($this->items as $item) {
            if (! $item->isSafe()) {
                return true;
            }
        }

        return false;
    }

    public function countBy(SynchronizationClassification $classification): int
    {
        return count(array_filter($this->items, static fn (SynchronizationPlanItem $i): bool => $i->classification === $classification));
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return [
            'total' => count($this->items),
            'safe' => $this->countBy(SynchronizationClassification::Safe),
            'review_required' => $this->countBy(SynchronizationClassification::ReviewRequired),
            'conflict' => $this->countBy(SynchronizationClassification::Conflict),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'summary' => $this->summary(),
            'items' => array_map(static fn (SynchronizationPlanItem $i): array => $i->toArray(), $this->items),
        ];
    }
}
