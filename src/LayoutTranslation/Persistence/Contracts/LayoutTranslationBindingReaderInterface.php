<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Persistence\Contracts;

use Plugins\PageBuilder\LayoutTranslation\Identity\LayoutKey;
use Plugins\PageBuilder\LayoutTranslation\Identity\PageTranslationKey;
use Plugins\PageBuilder\LayoutTranslation\Identity\TranslationGroupKey;
use Plugins\PageBuilder\LayoutTranslation\Persistence\Results\PersistedLayoutTranslationBinding;

/**
 * Read seam for persisted translation bindings — LT-2.
 *
 * Layer: Persistence / Contract. Reads are keyed by the frozen LT-1 identity value
 * objects and return domain contracts ({@see PersistedLayoutTranslationBinding}) —
 * never an Eloquent model, query builder, raw row or untyped array.
 */
interface LayoutTranslationBindingReaderInterface
{
    public function findByPageTranslationKey(PageTranslationKey $key): ?PersistedLayoutTranslationBinding;

    public function findByLayoutKey(LayoutKey $key): ?PersistedLayoutTranslationBinding;

    /**
     * @return array<int, PersistedLayoutTranslationBinding>
     */
    public function findByTranslationGroupKey(TranslationGroupKey $key): array;
}
