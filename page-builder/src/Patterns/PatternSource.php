<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Patterns;

/**
 * A discovery source: a directory to scan, a stable id, and a priority. On a
 * duplicate pattern id the highest priority wins (theme > plugin packs > core).
 */
final class PatternSource
{
    public function __construct(
        public readonly string $id,
        public readonly string $path,
        public readonly int $priority = 0,
    ) {}
}
