<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Baseline\Repositories;

use Plugins\PageBuilder\LayoutTranslation\Baseline\Contracts\LayoutBaselineRepositoryInterface;
use Plugins\PageBuilder\LayoutTranslation\Baseline\LayoutBaseline;
use Plugins\PageBuilder\LayoutTranslation\Baseline\Models\LayoutTranslationBaseline;

/**
 * Eloquent-backed baseline repository (LT-4).
 *
 * Layer: Translation (baseline / repository). Always hydrates the domain
 * {@see LayoutBaseline}; an Eloquent model never crosses this boundary. `record()`
 * upserts by page-translation key (one baseline per target). It participates in a
 * caller's transaction and opens none of its own.
 */
final class EloquentLayoutBaselineRepository implements LayoutBaselineRepositoryInterface
{
    public function find(string $pageTranslationKey): ?LayoutBaseline
    {
        $model = LayoutTranslationBaseline::query()->where('page_translation_key', $pageTranslationKey)->first();

        return $model === null ? null : $this->hydrate($model);
    }

    public function record(LayoutBaseline $baseline): LayoutBaseline
    {
        LayoutTranslationBaseline::query()->updateOrCreate(
            ['page_translation_key' => $baseline->pageTranslationKey],
            [
                'source_layout_id' => $baseline->sourceLayoutId,
                'fingerprint' => $baseline->fingerprint,
                'captured_at' => $baseline->capturedAt,
            ],
        );

        return $baseline;
    }

    private function hydrate(LayoutTranslationBaseline $model): LayoutBaseline
    {
        return new LayoutBaseline(
            (string) $model->page_translation_key,
            (int) $model->source_layout_id,
            (string) $model->fingerprint,
            (string) $model->captured_at,
        );
    }
}
