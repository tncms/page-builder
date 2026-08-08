<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Persistence;

use Plugins\PageBuilder\LayoutTranslation\Persistence\Contracts\LayoutDocumentLocatorInterface;
use Plugins\PageBuilder\Models\PageBuilderLayout;

/**
 * Resolves pb_layouts document existence — LT-2.
 *
 * Layer: Persistence. Reads the canonical layout-document store to answer whether a
 * referenced `pb_layouts.id` still exists; it never writes.
 */
final class EloquentLayoutDocumentLocator implements LayoutDocumentLocatorInterface
{
    public function exists(int $layoutId): bool
    {
        return PageBuilderLayout::query()->whereKey($layoutId)->exists();
    }
}
