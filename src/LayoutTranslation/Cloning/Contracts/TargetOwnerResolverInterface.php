<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Cloning\Contracts;

use Plugins\PageBuilder\LayoutTranslation\Identity\PageTranslationKey;
use Plugins\PageBuilder\Ownership\LayoutOwnerInterface;

/**
 * Resolves the generic Layout Owner for a target page translation (LT-3, Option D).
 *
 * Layer: Translation (contract). It returns a generic {@see LayoutOwnerInterface}
 * (Page Builder never learns which owner type it is) or null when the page
 * translation does not exist (→ TargetNotFound). The clone workflow depends on this
 * abstraction, not on the concrete Core-reading implementation.
 */
interface TargetOwnerResolverInterface
{
    public function resolve(PageTranslationKey $translation): ?LayoutOwnerInterface;
}
