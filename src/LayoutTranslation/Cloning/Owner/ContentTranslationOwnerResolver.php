<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Cloning\Owner;

use Plugins\PageBuilder\LayoutTranslation\Cloning\Contracts\TargetOwnerResolverInterface;
use Plugins\PageBuilder\LayoutTranslation\Identity\PageTranslationKey;
use TheNguyen\CMS\Models\ContentTranslation;

/**
 * Resolves the {@see ContentTranslationOwner} for a page translation (LT-3).
 *
 * Layer: Translation (LayoutTranslation). It reads Core's `cms_content_translations`
 * to find the `ContentTranslation.id` for a page translation's `(content_id,
 * locale)` and wraps it as a generic Layout Owner. It returns null when the page
 * translation does not exist (fail closed — the caller maps this to TargetNotFound).
 * Reading Core is a Translation-layer concern; Page Builder core never does this.
 */
final class ContentTranslationOwnerResolver implements TargetOwnerResolverInterface
{
    public function resolve(PageTranslationKey $translation): ?ContentTranslationOwner
    {
        $row = ContentTranslation::query()
            ->where('content_id', $translation->group()->ownerId)
            ->where('locale', $translation->locale())
            ->first();

        return $row === null ? null : new ContentTranslationOwner((int) $row->getKey());
    }
}
