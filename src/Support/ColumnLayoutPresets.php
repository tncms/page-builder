<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Support;

/**
 * The built-in column-layout catalog for the canvas Layout popup (Phase 26.1;
 * enriched to rich metadata in Phase 26.1.1).
 *
 * Each entry is a column-proportion preset applied to an EXISTING container: an
 * ordered list of column widths (percent strings the {@see ColumnWidth} renderer
 * already understands) plus presentation metadata — category, description,
 * keywords (for search), featured/recommended flags, ordering, and a lightweight
 * `preview` descriptor derived from the widths (a data descriptor the popup turns
 * into inline SVG/bars — never a binary image). This is distinct from
 * {@see LayoutPresets}, which ships whole section→container→column trees for the
 * insert-pattern palette; here we only re-proportion columns.
 *
 * @phpstan-type ColumnLayoutPreset array{id: string, label: string, widths: array<int, string>, category: string, icon: string, description: string, keywords: array<int, string>, featured: bool, recommended: bool, order: int, preview: array{type: string, segments: array<int, float>}}
 */
final class ColumnLayoutPresets
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            self::preset('full', '100', ['100'], 'common', 'columns-1', 'Single full-width column', ['1', 'one', 'single', 'full'], 10, true, true),
            self::preset('halves', '50 / 50', ['50', '50'], 'common', 'columns-2', 'Two equal columns', ['2', 'two', 'equal', 'half', 'halves'], 20, true, true),
            self::preset('third-seventh', '30 / 70', ['30', '70'], 'sidebar', 'columns-2', 'Narrow left sidebar', ['2', 'two', 'sidebar', 'left'], 30, true, false),
            self::preset('seventh-third', '70 / 30', ['70', '30'], 'sidebar', 'columns-2', 'Narrow right sidebar', ['2', 'two', 'sidebar', 'right'], 40, false, false),
            self::preset('quarter-three', '25 / 75', ['25', '75'], 'sidebar', 'columns-2', 'Slim left sidebar', ['2', 'two', 'sidebar', 'left', 'quarter'], 50, false, false),
            self::preset('three-quarter', '75 / 25', ['75', '25'], 'sidebar', 'columns-2', 'Slim right sidebar', ['2', 'two', 'sidebar', 'right', 'quarter'], 60, false, false),
            self::preset('third-twothirds', '33 / 66', ['33.3333', '66.6667'], 'sidebar', 'columns-2', 'One-third left sidebar', ['2', 'two', 'sidebar', 'third'], 70, false, false),
            self::preset('twothirds-third', '66 / 33', ['66.6667', '33.3333'], 'sidebar', 'columns-2', 'One-third right sidebar', ['2', 'two', 'sidebar', 'third'], 80, false, false),
            self::preset('thirds', '33 / 33 / 33', ['33.3333', '33.3333', '33.3333'], 'equal', 'columns-3', 'Three equal columns', ['3', 'three', 'equal', 'thirds'], 90, true, true),
            self::preset('centered-halves', '25 / 50 / 25', ['25', '50', '25'], 'common', 'columns-3', 'Centered content with side rails', ['3', 'three', 'center', 'centered'], 100, false, false),
            self::preset('centered-wide', '20 / 60 / 20', ['20', '60', '20'], 'common', 'columns-3', 'Wide centered content', ['3', 'three', 'center', 'centered', 'wide'], 110, false, true),
            self::preset('quarters', '25 × 4', ['25', '25', '25', '25'], 'equal', 'columns-4', 'Four equal columns', ['4', 'four', 'equal', 'quarters'], 120, true, false),
            self::preset('fifths', '20 × 5', ['20', '20', '20', '20', '20'], 'equal', 'columns-5', 'Five equal columns', ['5', 'five', 'equal', 'fifths'], 130, false, false),
            self::preset('golden', '38 / 62', ['38', '62'], 'common', 'columns-2', 'Golden-ratio left', ['2', 'two', 'golden', 'ratio'], 140, false, false),
            self::preset('golden-reverse', '62 / 38', ['62', '38'], 'common', 'columns-2', 'Golden-ratio right', ['2', 'two', 'golden', 'ratio'], 150, false, false),
        ];
    }

    /**
     * @param  array<int, string>  $widths
     * @param  array<int, string>  $keywords
     * @return array<string, mixed>
     */
    private static function preset(
        string $id,
        string $label,
        array $widths,
        string $category,
        string $icon,
        string $description,
        array $keywords,
        int $order,
        bool $featured,
        bool $recommended,
    ): array {
        return [
            'id' => $id,
            'label' => $label,
            'widths' => array_values($widths),
            'category' => $category,
            'icon' => $icon,
            'description' => $description,
            'keywords' => array_values($keywords),
            'featured' => $featured,
            'recommended' => $recommended,
            'order' => $order,
            // Data descriptor the popup renders as proportion bars / inline SVG.
            'preview' => [
                'type' => 'bars',
                'segments' => array_map(static fn (string $width): float => (float) $width, array_values($widths)),
            ],
        ];
    }
}
