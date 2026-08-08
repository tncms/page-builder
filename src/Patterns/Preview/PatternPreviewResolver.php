<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Patterns\Preview;

use Plugins\PageBuilder\Patterns\Pattern;

/**
 * Public entry point for pattern previews (Phase 25.1, §9). Given a pattern it picks
 * the provider for the manifest's declared `preview.type` and returns a normalised
 * preview descriptor. Adding `render` / `generated` later needs no change here — a
 * new provider is registered and this resolver keeps its stable API.
 */
final class PatternPreviewResolver
{
    public function __construct(private readonly PreviewProviderRegistry $registry) {}

    /**
     * @return array{type: string, src: string, available: bool, fallback: string}
     */
    public function preview(Pattern $pattern): array
    {
        $provider = $this->registry->forType($pattern->manifest()->previewType());

        return $provider->resolve($pattern)->toArray();
    }

    /**
     * The absolute, on-disk path of a pattern's streamable thumbnail image, or null
     * when there is none to stream. This is the ONE place that turns the normalised
     * preview into a file path so the controller never guesses the raw manifest
     * shape (§9). Deterministic source priority: `preview.src` → `preview.desktop`
     * → legacy `thumbnail` (the normaliser already collapses src→desktop→thumbnail
     * into `src`, with `responsive.desktop` kept as the explicit next fallback).
     *
     * The resolved file is constrained to the pattern's own directory via a realpath
     * prefix check, so a crafted `../../secret` preview source can never escape the
     * pack (§12 traversal protection). Returns null — never throws — for non-image
     * previews, empty sources, missing files or traversal attempts, so the
     * controller can honour a single missing-thumbnail contract.
     */
    public function thumbnailFile(Pattern $pattern): ?string
    {
        $preview = $this->preview($pattern);

        if (($preview['type'] ?? 'image') !== 'image') {
            return null;
        }

        $src = trim((string) ($preview['src'] ?? ''));
        if ($src === '') {
            $src = trim((string) ($preview['responsive']['desktop'] ?? ''));
        }
        if ($src === '') {
            return null;
        }

        $directory = $pattern->directory();
        if ($directory === '') {
            return null;
        }

        $base = realpath($directory);
        $full = realpath($directory.DIRECTORY_SEPARATOR.$src);
        if ($base === false || $full === false) {
            return null;
        }

        $baseWithSep = rtrim($base, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        if (! str_starts_with($full, $baseWithSep)) {
            return null;
        }

        return is_file($full) ? $full : null;
    }
}
