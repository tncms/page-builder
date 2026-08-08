<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Review\Contracts;

use Plugins\PageBuilder\LayoutTranslation\Identity\PageTranslationKey;
use Plugins\PageBuilder\LayoutTranslation\Review\Preview\PreviewFragment;

/**
 * Resolves the render-only preview fragments for one origin (LT-8B, per
 * LT-8A-REVIEW-WORKSPACE-ARCHITECTURE §6).
 *
 * Layer: Translation (review boundary). The concrete implementation reads through the LOCK-FREE read
 * seams (never the execution loader's `loadForUpdate`) and returns presentation fragments only. Preview
 * is a convenience, not truth: it may read state that has drifted since the plan was built; the sole
 * authoritative staleness verdict is the coordinator's provenance check at submit (INV-LT-8A-04). Content
 * crosses this seam ONE-DIRECTIONALLY, to the screen only (INV-LT-8A-02).
 */
interface PreviewProjectionInterface
{
    public function fragmentFor(PageTranslationKey $target, string $origin): PreviewFragment;
}
