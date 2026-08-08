<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Execution\Contracts;

use Plugins\PageBuilder\LayoutTranslation\Execution\Loading\LoadedSynchronizationState;
use Plugins\PageBuilder\LayoutTranslation\Identity\PageTranslationKey;

/**
 * Loads the canonical persisted state for a synchronization (LT-7B3).
 *
 * Layer: Translation (execution loading). `loadForUpdate` is called inside the coordinator's transaction
 * and MUST lock the target's row(s) so provenance can be verified exactly once under the lock
 * (INV-LT-7-EXEC-01); `load` is a lock-free read used by diagnostics. Both return an immutable
 * {@see LoadedSynchronizationState}, never an Eloquent model or row.
 */
interface SynchronizationStateLoaderInterface
{
    public function loadForUpdate(PageTranslationKey $target): LoadedSynchronizationState;

    public function load(PageTranslationKey $target): LoadedSynchronizationState;
}
