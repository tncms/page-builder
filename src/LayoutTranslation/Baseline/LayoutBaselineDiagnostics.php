<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Baseline;

use Plugins\PageBuilder\LayoutTranslation\Baseline\Contracts\LayoutBaselineRepositoryInterface;
use Plugins\PageBuilder\LayoutTranslation\Correspondence\NodeCorrespondence;
use Plugins\PageBuilder\LayoutTranslation\Identity\PageTranslationKey;
use Plugins\PageBuilder\LayoutTranslation\Persistence\Contracts\LayoutTranslationBindingReaderInterface;
use Plugins\PageBuilder\Models\PageBuilderLayout;
use Throwable;

/**
 * Never-throwing, read-only diagnostics for baseline + correspondence (LT-4).
 *
 * Layer: Translation (baseline / diagnostics). Reports baseline presence and
 * fingerprint, and the target's correspondence coverage (total / stamped /
 * target-owned / duplicate origins). It never mutates or repairs storage, and never
 * logs full payloads.
 */
final class LayoutBaselineDiagnostics
{
    private const FEATURE = 'Page Builder Layout Translation Baseline (LT-4)';

    public function __construct(
        private readonly LayoutBaselineRepositoryInterface $baselines,
        private readonly LayoutTranslationBindingReaderInterface $bindings,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function report(PageTranslationKey $target): array
    {
        try {
            $baseline = $this->baselines->find($target->value());
            $binding = $this->bindings->findByPageTranslationKey($target);

            $correspondence = null;
            if ($binding !== null) {
                $model = PageBuilderLayout::query()->whereKey($binding->layoutId())->first();
                if ($model !== null) {
                    $correspondence = NodeCorrespondence::report(is_array($model->draft_payload) ? $model->draft_payload : []);
                }
            }

            return [
                'feature' => self::FEATURE,
                'page_translation' => $target->value(),
                'has_baseline' => $baseline !== null,
                'baseline' => $baseline?->toArray(),
                'bound' => $binding !== null,
                'correspondence' => $correspondence,
            ];
        } catch (Throwable $e) {
            return [
                'feature' => self::FEATURE,
                'error' => $e->getMessage(),
            ];
        }
    }
}
