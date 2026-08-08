<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Persistence\Contracts;

use Plugins\PageBuilder\LayoutTranslation\Identity\PageTranslationKey;
use Plugins\PageBuilder\LayoutTranslation\Persistence\Results\BindingWriteResult;
use Plugins\PageBuilder\LayoutTranslation\Persistence\Results\PersistedLayoutTranslationBinding;
use Plugins\PageBuilder\LayoutTranslation\State\LayoutTranslationStatus;

/**
 * Write seam for persisted translation bindings — LT-2.
 *
 * Layer: Persistence / Contract. Extends the read seam and adds only
 * relationship-level writes. Every write returns a typed {@see BindingWriteResult}
 * — never a model or raw row. It performs NO clone, diff, merge, synchronization
 * or baseline work, and never invents identity or derives capability.
 */
interface LayoutTranslationBindingRepositoryInterface extends LayoutTranslationBindingReaderInterface
{
    public function createBinding(PersistedLayoutTranslationBinding $binding): BindingWriteResult;

    public function updatePersistedStatus(PageTranslationKey $key, LayoutTranslationStatus $status): BindingWriteResult;

    public function removeBinding(PageTranslationKey $key): BindingWriteResult;
}
