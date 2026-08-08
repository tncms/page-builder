<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Community\Discovery;

/**
 * The immutable result of a discovery query (Phase 26.50D, Rule 6).
 *
 * It carries the page of matched {@see CatalogMetadata} plus pagination metadata
 * (`total` across all pages, current `page`, `perPage`). It holds metadata only — no
 * payload — so a discovery result is safe to serialise for a UI or an API without ever
 * touching resource content (Rule 1).
 *
 * @see docs/discovery-service.md
 */
final class DiscoveryResult
{
    /**
     * @param  array<int, CatalogMetadata>  $items  the current page
     */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $page = 1,
        public readonly int $perPage = DiscoveryQuery::ALL,
    ) {}

    /**
     * @return array<int, CatalogMetadata>
     */
    public function items(): array
    {
        return $this->items;
    }

    /**
     * @return array<int, string>
     */
    public function rids(): array
    {
        return array_map(static fn (CatalogMetadata $m): string => $m->rid, $this->items);
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    public function pageCount(): int
    {
        if ($this->perPage <= DiscoveryQuery::ALL) {
            return $this->total === 0 ? 0 : 1;
        }

        return (int) ceil($this->total / $this->perPage);
    }

    public function hasMore(): bool
    {
        return $this->page < $this->pageCount();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'items' => array_map(static fn (CatalogMetadata $m): array => $m->toArray(), $this->items),
            'total' => $this->total,
            'page' => $this->page,
            'per_page' => $this->perPage,
            'page_count' => $this->pageCount(),
            'has_more' => $this->hasMore(),
        ];
    }
}
