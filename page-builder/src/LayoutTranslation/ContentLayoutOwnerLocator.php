<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation;

use Plugins\PageBuilder\LayoutTranslation\Contracts\LayoutOwnerLocatorInterface;
use Plugins\PageBuilder\Support\EditableOwners;

/**
 * Resolves owner existence through the existing {@see EditableOwners} authority,
 * which maps an owner type + id to its TN CMS Content row (LT-1).
 */
final class ContentLayoutOwnerLocator implements LayoutOwnerLocatorInterface
{
    public function exists(string $ownerType, int|string $ownerId): bool
    {
        return EditableOwners::resolve($ownerType, $ownerId) !== null;
    }
}
