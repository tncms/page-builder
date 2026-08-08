<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Baseline\Contracts;

use Plugins\PageBuilder\LayoutTranslation\Baseline\LayoutBaseline;

/**
 * Persistence seam for source baselines (LT-4).
 *
 * Layer: Translation (baseline / contract). Reads and writes baselines keyed by the
 * canonical page-translation key value, returning domain contracts
 * ({@see LayoutBaseline}) — never an Eloquent model or row. `record()` is an upsert
 * (one baseline per target; re-baselining replaces it). This LT-4 store is separate
 * from the frozen LT-2 binding store, which is left untouched.
 */
interface LayoutBaselineRepositoryInterface
{
    public function find(string $pageTranslationKey): ?LayoutBaseline;

    public function record(LayoutBaseline $baseline): LayoutBaseline;
}
