<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Builder\Palette;

/**
 * A source group within a kind's palette (Phase 26.47E, Rule 5).
 *
 * Groups are contributed by providers — Built-in and Imported Designs today;
 * My Designs / Marketplace / Favorites are reserved (not implemented). A group
 * holds ordered categories of items and never duplicates a resource.
 */
final class PaletteGroup
{
    // Reserved group keys (Rule 5) — declared for future providers, not populated.
    public const GROUP_BUILTIN = 'builtin';
    public const GROUP_IMPORTED = 'imported';
    public const GROUP_MY_DESIGNS = 'my-designs';   // reserved (Phase 26.48)
    public const GROUP_MARKETPLACE = 'marketplace'; // reserved (Phase 26.51)
    public const GROUP_FAVORITES = 'favorites';     // reserved

    /**
     * @param  array<int, PaletteCategory>  $categories
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly array $categories,
    ) {
    }

    public function itemCount(): int
    {
        return array_reduce(
            $this->categories,
            static fn (int $carry, PaletteCategory $category): int => $carry + count($category->items),
            0,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'categories' => array_map(static fn (PaletteCategory $category): array => $category->toArray(), $this->categories),
        ];
    }
}
