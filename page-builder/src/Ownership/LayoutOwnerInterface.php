<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Ownership;

/**
 * A generic Page Builder Layout Owner — the storage attachment point of a layout
 * document (LT-3, Option D).
 *
 * Layer: Page Builder core (Ownership). Translation-agnostic: Page Builder
 * understands Layout Owners, never Translation. An owner identifies WHERE a layout
 * document attaches in `pb_layouts` (`owner_type`, `owner_id`); it is never a
 * translation identity (that belongs to LT-1). Plugins implement new owner types
 * (Translation, Product, Landing, …) without modifying Page Builder.
 */
interface LayoutOwnerInterface
{
    public function ownerType(): string;

    public function ownerId(): int|string;
}
