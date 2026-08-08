<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Builder\Palette;

/**
 * A category bucket within a palette group (Phase 26.47E, Rule 12).
 *
 * Categories group items by their Design Resource `category` (Layout, Hero,
 * Features, Pricing, …). Any future source inherits the same category model
 * automatically because the category comes from the resource, not the provider.
 */
final class PaletteCategory
{
    /**
     * @param  array<int, PaletteItem>  $items
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly array $items,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'items' => array_map(static fn (PaletteItem $item): array => $item->toArray(), $this->items),
        ];
    }
}
