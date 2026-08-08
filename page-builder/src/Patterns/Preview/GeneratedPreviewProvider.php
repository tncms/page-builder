<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Patterns\Preview;

use Plugins\PageBuilder\Patterns\Pattern;

/**
 * Architecture seam for the future "generated" preview strategy (Phase 25.1,
 * §8/§13): a preview image produced by the {@see \Plugins\PageBuilder\Patterns\Screenshot\PatternScreenshotGenerator}
 * (Builder runtime → headless browser → PNG/WEBP). Intentionally NOT implemented
 * yet — always unavailable, falling back to an image.
 */
final class GeneratedPreviewProvider implements PreviewProvider
{
    public function type(): string
    {
        return 'generated';
    }

    public function supports(string $type): bool
    {
        return $type === 'generated';
    }

    public function resolve(Pattern $pattern): PatternPreview
    {
        // Not implemented (architecture only) — degrade to the image strategy.
        return new PatternPreview('generated', $pattern->manifest()->previewSrc(), false, 'image', $pattern->manifest()->responsivePreview());
    }
}
