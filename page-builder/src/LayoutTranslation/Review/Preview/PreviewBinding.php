<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Review\Preview;

use Plugins\PageBuilder\LayoutTranslation\Identity\PageTranslationKey;
use Plugins\PageBuilder\LayoutTranslation\Review\Contracts\PreviewProjectionInterface;

/**
 * Binds a session's target to the preview projection (LT-8B, per
 * LT-8A-REVIEW-WORKSPACE-ARCHITECTURE §4/§6).
 *
 * Layer: Translation (review preview / query). A thin, stateless query binder on the QUERY side of CQRS
 * (INV-LT-8A-08). It fetches a render-only {@see PreviewFragment} for an origin and hands it to the
 * renderer; it holds no authoritative state (INV-LT-8A-07) and never routes content back into the command
 * side (INV-LT-8A-02). It exposes no apply()/save()/accept()/reject().
 */
final class PreviewBinding
{
    public function __construct(
        private readonly PreviewProjectionInterface $projection,
        private readonly PageTranslationKey $target,
    ) {}

    public function fragmentFor(string $origin): PreviewFragment
    {
        return $this->projection->fragmentFor($this->target, $origin);
    }
}
