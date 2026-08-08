<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Execution\Adapters;

use Plugins\PageBuilder\LayoutTranslation\Baseline\Contracts\LayoutBaselineRepositoryInterface;
use Plugins\PageBuilder\LayoutTranslation\Baseline\LayoutBaseline;
use Plugins\PageBuilder\LayoutTranslation\Execution\Contracts\BaselineAdvancerInterface;
use Plugins\PageBuilder\LayoutTranslation\Identity\PageTranslationKey;

/**
 * Production baseline advancer (LT-7C, per LT-0-ADR-001 D10).
 *
 * Layer: Translation (execution adapter). Advances the target's source baseline to the just-synced
 * source fingerprint via the frozen LT-4 repository upsert (one baseline per target). It participates in
 * the coordinator's transaction. Reads/uses `date('c')` for the capture timestamp — an environmental
 * concern that belongs to the coordinator layer, never the mutation engine (INV-LT-7-MUT-01).
 */
final class LayoutBaselineAdvancer implements BaselineAdvancerInterface
{
    public function __construct(private readonly LayoutBaselineRepositoryInterface $baselines) {}

    public function advance(PageTranslationKey $target, int $sourceLayoutId, string $fingerprint): void
    {
        $this->baselines->record(new LayoutBaseline($target->value(), $sourceLayoutId, $fingerprint, date('c')));
    }
}
