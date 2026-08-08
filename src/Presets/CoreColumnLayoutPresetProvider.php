<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Presets;

use Plugins\PageBuilder\Presets\Contracts\ColumnLayoutPresetProvider;
use Plugins\PageBuilder\Support\ColumnLayoutPresets;

/**
 * The built-in column-layout presets (Phase 26.1.1). Priority 0 — the baseline
 * every contributor layers on top of. Delegates to the {@see ColumnLayoutPresets}
 * catalog so the preset data itself stays declarative in one place.
 */
final class CoreColumnLayoutPresetProvider implements ColumnLayoutPresetProvider
{
    public function presets(): array
    {
        return ColumnLayoutPresets::all();
    }

    public function priority(): int
    {
        return 0;
    }
}
