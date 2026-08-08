<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\TimelineInfrastructure;

/**
 * An immutable, paginated view of timeline entries for a list surface (LT-9C).
 *
 * Layer: Translation (timeline application / read model). Derived from an immutable runtime projection;
 * never persisted. Each item is a payload-safe entry array plus a `has_gap` flag — never raw snapshot
 * content (INV-LT-9C-04).
 */
final class TimelineListResult
{
    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $page,
        public readonly int $perPage,
    ) {}

    public function pageCount(): int
    {
        return $this->perPage > 0 ? (int) ceil($this->total / $this->perPage) : 0;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'items' => $this->items,
            'total' => $this->total,
            'page' => $this->page,
            'per_page' => $this->perPage,
            'page_count' => $this->pageCount(),
        ];
    }
}
