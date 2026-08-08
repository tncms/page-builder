<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Contracts;

use Plugins\PageBuilder\LayoutTranslation\State\LayoutTranslationStatus;

/**
 * The single, minimal storage seam behind the descriptor boundary (LT-1).
 *
 * It reports only the multi-dimensional {@see LayoutTranslationStatus} of a page
 * translation's layout — never raw storage entities — so the resolver and
 * validator stay persistence-independent. Persistent locale-layout storage is a
 * later phase (LT-2); this seam keeps LT-1 free of it while remaining swappable.
 */
interface LayoutTranslationStateReaderInterface
{
    public function statusFor(
        string $ownerType,
        int|string $ownerId,
        string $locale,
        bool $isSourceLocale,
    ): LayoutTranslationStatus;
}
