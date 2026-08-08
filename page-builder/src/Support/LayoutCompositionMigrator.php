<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Support;

/**
 * OPTIONAL helper (Phase 25.3, §10) that upgrades a legacy layout — a section whose
 * direct children are element blocks — into the composition tree
 * Section → Container → Column → Elements. It is NEVER run automatically: existing
 * pages keep their stored payload untouched. Callers (e.g. a future opt-in "convert
 * to layout" action) invoke it explicitly on a payload they choose to migrate.
 *
 * The transform is idempotent: a section already using a Container is left as-is.
 */
final class LayoutCompositionMigrator
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function migratePayload(array $payload): array
    {
        $sections = is_array($payload['sections'] ?? null) ? $payload['sections'] : [];

        $payload['sections'] = array_map(
            fn (mixed $section): mixed => is_array($section) ? $this->migrateSection($section) : $section,
            $sections,
        );

        return $payload;
    }

    /**
     * Repair a legacy section into the composition tree. No-op when the section is
     * empty or already composition-based (has a Container).
     *
     * Two legacy shapes are handled (Phase 25.3.2 §10):
     *   - ORPHANED COLUMNS — a section whose direct children are already `column`
     *     blocks (Section → Columns, no Container). These are wrapped DIRECTLY in a
     *     Container so each column is preserved: Section → Container → Columns.
     *     (Nesting them inside one wrapper column would create an invalid
     *     Column-inside-Column tree.)
     *   - ELEMENT BLOCKS — a section of plain elements is wrapped in one Container
     *     holding one full-width Column: Section → Container → Column → Elements.
     *
     * @param  array<string, mixed>  $section
     * @return array<string, mixed>
     */
    public function migrateSection(array $section): array
    {
        $blocks = is_array($section['blocks'] ?? null) ? $section['blocks'] : [];
        if ($blocks === [] || $this->isComposition($blocks)) {
            return $section;
        }

        $children = $this->allColumns($blocks)
            ? array_values($blocks)
            : [[
                'type' => 'column',
                'data' => ['width' => '100', 'blocks' => array_values($blocks)],
            ]];

        $section['blocks'] = [[
            'type' => 'container',
            'data' => ['layout' => 'flex', 'direction' => 'row', 'width' => 'normal', 'blocks' => $children],
        ]];

        return $section;
    }

    /**
     * Whether every direct block is already a `column` (an orphaned columns row).
     *
     * @param  array<int, mixed>  $blocks
     */
    private function allColumns(array $blocks): bool
    {
        foreach ($blocks as $block) {
            if (! is_array($block) || ($block['type'] ?? null) !== 'column') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, mixed>  $blocks
     */
    private function isComposition(array $blocks): bool
    {
        foreach ($blocks as $block) {
            if (is_array($block) && ($block['type'] ?? null) === 'container') {
                return true;
            }
        }

        return false;
    }
}
