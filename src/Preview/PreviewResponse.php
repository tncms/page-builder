<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Preview;

/**
 * Immutable result of a preview render.
 *
 * Holds the two trusted, server-generated fragments that compose the sandboxed
 * preview document:
 *   - head: the builder's runtime asset head (PageBuilderFrontendAssets::headHtml())
 *   - body: the rendered resource HTML (identical to the production frontend render)
 *
 * The response carries no interactive/editor markup — it is a read-only view.
 */
final class PreviewResponse
{
    public function __construct(
        public readonly string $kind,
        public readonly string $device,
        public readonly int $width,
        public readonly string $locale,
        public readonly string $head,
        public readonly string $body,
        public readonly string $hash,
    ) {
    }

    public function isEmpty(): bool
    {
        return trim($this->body) === '';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind,
            'device' => $this->device,
            'width' => $this->width,
            'locale' => $this->locale,
            'hash' => $this->hash,
            'head' => $this->head,
            'body' => $this->body,
        ];
    }
}
