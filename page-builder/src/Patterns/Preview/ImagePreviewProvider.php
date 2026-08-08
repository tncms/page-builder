<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Patterns\Preview;

use Plugins\PageBuilder\Patterns\Pattern;

/**
 * The only concrete preview provider today (Phase 25.1, §13): serves a static image
 * (`preview.svg` / `preview.webp`). The source comes from the manifest `preview.src`
 * (falling back to the thumbnail); `available` reflects whether that file exists on
 * disk, so the controller can decide whether to emit a URL or fall back.
 */
final class ImagePreviewProvider implements PreviewProvider
{
    public function type(): string
    {
        return 'image';
    }

    public function supports(string $type): bool
    {
        return $type === 'image';
    }

    public function resolve(Pattern $pattern): PatternPreview
    {
        $manifest = $pattern->manifest();
        $src = $manifest->previewSrc() !== '' ? $manifest->previewSrc() : $manifest->thumbnail;

        $available = false;
        if ($src !== '' && $pattern->directory() !== '') {
            $available = is_file($pattern->directory().DIRECTORY_SEPARATOR.$src);
        }

        return new PatternPreview('image', $src, $available, 'image', $manifest->responsivePreview());
    }
}
