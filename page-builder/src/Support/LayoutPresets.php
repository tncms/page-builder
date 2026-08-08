<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Support;

/**
 * Data-driven layout presets (Phase 25.3, §4). Each preset is a ready-to-insert
 * Section → Container → Column tree shaped exactly like the `insert-pattern`
 * command's `sections` argument, so inserting a preset is ONE undo entry through
 * the existing CommandBus (the editor dispatches `insert-pattern` with `sections`).
 *
 * Presets are pure metadata — no special section/block types, just composition of
 * the generic `section`, `container` and `column` definitions.
 */
final class LayoutPresets
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            self::preset('layout.1col', '1 Column', ['100']),
            self::preset('layout.2col', '2 Columns', ['50', '50']),
            self::preset('layout.3col', '3 Columns', ['33', '33', '33']),
            self::preset('layout.4col', '4 Columns', ['25', '25', '25', '25']),
            self::preset('layout.split', 'Left / Center / Right', ['25', '50', '25']),
        ];
    }

    /**
     * @param  array<int, string>  $widths
     * @return array<string, mixed>
     */
    private static function preset(string $id, string $label, array $widths): array
    {
        $columns = array_map(
            static fn (string $width): array => [
                'type' => 'column',
                'data' => ['width' => $width, 'blocks' => []],
            ],
            $widths,
        );

        return [
            'id' => $id,
            'label' => $label,
            'icon' => 'heroicon-o-view-columns',
            'columns' => count($widths),
            'sections' => [[
                'type' => 'section',
                'data' => ['width' => 'normal', 'padding' => 'md'],
                'blocks' => [[
                    'type' => 'container',
                    'data' => ['layout' => 'flex', 'width' => 'normal', 'gap' => 'md', 'blocks' => $columns],
                ]],
            ]],
        ];
    }
}
