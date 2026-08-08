<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Contracts;

/**
 * Reports whether a layout's owning content actually exists (LT-1).
 *
 * A narrow seam so the relationship validator can detect orphaned/dangling
 * layouts without depending on the ORM directly (keeping it unit-testable).
 */
interface LayoutOwnerLocatorInterface
{
    public function exists(string $ownerType, int|string $ownerId): bool;
}
