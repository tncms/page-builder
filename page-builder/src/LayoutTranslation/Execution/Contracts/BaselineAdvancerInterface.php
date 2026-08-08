<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Execution\Contracts;

use Plugins\PageBuilder\LayoutTranslation\Identity\PageTranslationKey;

/**
 * Advances the target's source baseline on a successful sync (LT-7B3, per LT-0-ADR-001 D10).
 *
 * Layer: Translation (execution baseline). A thin seam over the LT-4 baseline repository upsert. The
 * baseline advances to the full current source fingerprint (the state just synced from); it participates
 * in the coordinator's transaction. The mutation engine never touches the baseline (INV-LT-7-MUT-01).
 */
interface BaselineAdvancerInterface
{
    public function advance(PageTranslationKey $target, int $sourceLayoutId, string $fingerprint): void;
}
