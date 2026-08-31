<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Builder\Registry;

use Plugins\PageBuilder\Builder\Palette\PaletteGroup;

/**
 * Contributes the ACTIVE theme's section/block Design Resources to the editor
 * palette (PB-TEMPLATE-REGISTRY-1-H2).
 *
 * A thin palette projection over the ONE Design Resource Repository, exactly like
 * {@see BuiltInProvider} and {@see ImportedProvider}: it reads the repository only
 * (never scans files — H1's ThemeResourceProvider already did that), keeps the
 * `theme` source, and lets the shared base filter to palette kinds (section, block;
 * kit reserved) and derive a content-based revision. Theme whole-page templates
 * (kind=page) are intentionally NOT palette elements; they are applied through the
 * existing Full Page Import / Template Application flow, not inserted as a block.
 */
final class ThemeResourceBuilderProvider extends AbstractRepositoryResourceProvider
{
    public const KEY = 'theme';

    public function key(): string
    {
        return self::KEY;
    }

    public function group(): string
    {
        return PaletteGroup::GROUP_THEME;
    }

    public function groupLabel(): string
    {
        return 'Theme';
    }

    public function priority(): int
    {
        // After the platform's built-ins (10) so theme contributions group beneath
        // them, and before reserved marketplace tiers.
        return 20;
    }

    /**
     * @return array<int, string>
     */
    protected function sources(): array
    {
        return ['theme'];
    }
}
