<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Builder\Registry;

use Plugins\PageBuilder\Builder\Palette\PaletteGroup;

/**
 * Contributes locally imported Design Package section/block resources to the
 * palette (Phase 26.47E). Source: `imported` (ImportedResourceProvider, Phase
 * 26.47). Reads the repository only — imported designs appear natively in the
 * palette alongside built-in ones, with unified search (Rule 11).
 */
final class ImportedProvider extends AbstractRepositoryResourceProvider
{
    public const KEY = 'imported';

    public function key(): string
    {
        return self::KEY;
    }

    public function group(): string
    {
        return PaletteGroup::GROUP_IMPORTED;
    }

    public function groupLabel(): string
    {
        return 'Imported Designs';
    }

    public function priority(): int
    {
        return 20;
    }

    protected function sources(): array
    {
        return ['imported'];
    }
}
