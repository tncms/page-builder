<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Patterns\Screenshot;

use Plugins\PageBuilder\Patterns\Pattern;

/**
 * Contract for generating a pattern preview image from its Builder payload
 * (Phase 25.1, §12). This is ARCHITECTURE ONLY — no generation is implemented in
 * this phase. A future implementation will drive the Builder runtime through a
 * headless browser (Playwright) to render the pattern and capture a PNG/WEBP that
 * the `generated` preview strategy then serves.
 */
interface PatternScreenshotGenerator
{
    /** Whether this generator can produce a screenshot for the given pattern. */
    public function supports(Pattern $pattern): bool;

    /**
     * Generate a preview image for the pattern and return the absolute path to it,
     * or null when generation is unavailable.
     *
     * @param  array<string, mixed>  $options
     */
    public function generate(Pattern $pattern, array $options = []): ?string;
}
