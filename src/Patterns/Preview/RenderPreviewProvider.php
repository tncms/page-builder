<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Patterns\Preview;

use Plugins\PageBuilder\Patterns\Pattern;

/**
 * Architecture seam for the future "render" preview strategy (Phase 25.1, §8/§13):
 * previewing a pattern by rendering its Builder payload server-side. Intentionally
 * NOT implemented yet — it always reports unavailable and falls back to an image, so
 * a manifest may declare `preview.type = "render"` today without breaking anything.
 */
final class RenderPreviewProvider implements PreviewProvider
{
    public function type(): string
    {
        return 'render';
    }

    public function supports(string $type): bool
    {
        return $type === 'render';
    }

    public function resolve(Pattern $pattern): PatternPreview
    {
        // Not implemented (architecture only) — degrade to the image strategy.
        return new PatternPreview('render', $pattern->manifest()->previewSrc(), false, 'image', $pattern->manifest()->responsivePreview());
    }
}
