<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Patterns\Screenshot;

use Plugins\PageBuilder\Patterns\Pattern;

/**
 * Default {@see PatternScreenshotGenerator} binding (Phase 25.1, §12). Generation is
 * not implemented in this phase, so this no-op reports no support and produces
 * nothing. It exists so the service is always resolvable and a real generator can
 * be swapped in later without touching call sites.
 */
final class NullPatternScreenshotGenerator implements PatternScreenshotGenerator
{
    public function supports(Pattern $pattern): bool
    {
        return false;
    }

    public function generate(Pattern $pattern, array $options = []): ?string
    {
        return null;
    }
}
