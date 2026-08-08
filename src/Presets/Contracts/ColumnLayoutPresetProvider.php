<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Presets\Contracts;

/**
 * Contributes column-layout presets to the canvas Layout popup (Phase 26.1.1).
 *
 * External plugins and themes implement this and register with the
 * {@see \Plugins\PageBuilder\Presets\ColumnLayoutPresetRegistry} to add their own
 * presets (e.g. a commerce plugin's product layouts, a theme's magazine layouts).
 * Discovery mirrors the Pattern Library: providers are merged by priority and
 * de-duplicated by id, so a higher-priority provider can override a core preset.
 */
interface ColumnLayoutPresetProvider
{
    /**
     * The rich preset descriptors this provider contributes.
     *
     * Each entry is an array with at least {id, label, widths}; the optional
     * {category, icon, description, keywords, featured, recommended, preview,
     * order} fields drive the popup's grouping, search, and visual preview.
     *
     * @return array<int, array<string, mixed>>
     */
    public function presets(): array;

    /**
     * Merge priority. Higher wins when two providers emit the same preset id.
     * Core ships at priority 0; contributors should use a positive value.
     */
    public function priority(): int;
}
