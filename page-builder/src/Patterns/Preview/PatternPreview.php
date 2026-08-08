<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Patterns\Preview;

/**
 * Immutable result of resolving a pattern's preview (Phase 25.1, §9). Describes HOW
 * to preview a pattern (`type`), the source asset (`src`, relative to the pattern
 * directory), whether that asset is actually available, and the `fallback` type to
 * use when it is not. The public URL is composed by the controller (it owns routing).
 */
final class PatternPreview
{
    /**
     * @param  array<string, string>  $responsive  Optional per-breakpoint sources (Phase 25.2.x, Part 7).
     */
    public function __construct(
        public readonly string $type,
        public readonly string $src = '',
        public readonly bool $available = false,
        public readonly string $fallback = 'image',
        public readonly array $responsive = [],
    ) {}

    /**
     * @return array{type: string, src: string, available: bool, fallback: string, responsive: array<string, string>}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'src' => $this->src,
            'available' => $this->available,
            'fallback' => $this->fallback,
            'responsive' => $this->responsive,
        ];
    }
}
