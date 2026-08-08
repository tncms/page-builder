<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Builder\Registry;

use Plugins\PageBuilder\Builder\Palette\PaletteGroup;

/**
 * Contributes the platform's Built-in section/block resources to the palette
 * (Phase 26.47E). Sources: `builtin` (LocalResourceProvider) and `starter`
 * (StarterResourceProvider). Reads the repository only — never scans files.
 */
final class BuiltInProvider extends AbstractRepositoryResourceProvider
{
    public const KEY = 'builtin';

    public function key(): string
    {
        return self::KEY;
    }

    public function group(): string
    {
        return PaletteGroup::GROUP_BUILTIN;
    }

    public function groupLabel(): string
    {
        return 'Built-in';
    }

    public function priority(): int
    {
        return 10;
    }

    protected function sources(): array
    {
        return ['builtin', 'starter'];
    }
}
