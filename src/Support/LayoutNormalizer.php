<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Support;

/**
 * Normalizes a stored (nullable / partial / untrusted) layout payload into the
 * canonical Page Builder layout shape (Phase 3).
 *
 * Everything downstream — rendering especially — can then assume a predictable
 * structure without defensive checks. The output is always:
 *
 *   [
 *     'schema'   => 'pagebuilder/01',
 *     'sections' => [
 *       ['type' => string, 'id' => ?string, 'data' => array, 'blocks' => [
 *         ['type' => string, 'id' => ?string, 'data' => array],
 *       ]],
 *     ],
 *   ]
 *
 * Malformed nodes (non-arrays, missing/blank `type`) are dropped rather than
 * throwing, so a corrupt draft degrades to a partial (or empty) layout instead
 * of a 500. Only the known keys survive; unknown keys are discarded.
 */
class LayoutNormalizer
{
    /** Layout schema identifier (versioned, so future shapes can migrate). */
    public const SCHEMA = 'pagebuilder/01';

    /**
     * Normalize a raw payload into the canonical layout shape.
     *
     * @param  array<string, mixed>|null  $payload
     * @return array{schema: string, sections: array<int, array<string, mixed>>}
     */
    public function normalize(?array $payload): array
    {
        $rawSections = is_array($payload['sections'] ?? null) ? $payload['sections'] : [];

        $sections = [];
        foreach ($rawSections as $section) {
            $normalized = $this->normalizeSection($section);
            if ($normalized !== null) {
                $sections[] = $normalized;
            }
        }

        return [
            'schema' => self::SCHEMA,
            'sections' => $sections,
        ];
    }

    /**
     * @return array{type: string, id: ?string, data: array<string, mixed>, blocks: array<int, array<string, mixed>>}|null
     */
    private function normalizeSection(mixed $section): ?array
    {
        if (! is_array($section)) {
            return null;
        }

        $type = $this->type($section);
        if ($type === null) {
            return null;
        }

        $rawBlocks = is_array($section['blocks'] ?? null) ? $section['blocks'] : [];

        $blocks = [];
        foreach ($rawBlocks as $block) {
            $normalized = $this->normalizeBlock($block);
            if ($normalized !== null) {
                $blocks[] = $normalized;
            }
        }

        return [
            'type' => $type,
            'id' => $this->id($section),
            'data' => $this->data($section),
            'blocks' => $blocks,
        ];
    }

    /**
     * @return array{type: string, id: ?string, data: array<string, mixed>}|null
     */
    private function normalizeBlock(mixed $block): ?array
    {
        if (! is_array($block)) {
            return null;
        }

        $type = $this->type($block);
        if ($type === null) {
            return null;
        }

        $data = $this->data($block);

        // Composition tree (Phase 25.3): layout blocks (Container / Column) hold
        // nested children in data.blocks. Normalize them recursively so the renderer
        // sees a uniform, sanitized tree without duplicating renderer logic. Blocks
        // without nested children are untouched (backward compatible).
        if (is_array($data['blocks'] ?? null)) {
            $children = [];
            foreach ($data['blocks'] as $child) {
                $normalized = $this->normalizeBlock($child);
                if ($normalized !== null) {
                    $children[] = $normalized;
                }
            }
            $data['blocks'] = $children;
        }

        return [
            'type' => $type,
            'id' => $this->id($block),
            'data' => $data,
        ];
    }

    /**
     * A non-empty string `type`, or null when absent/blank.
     *
     * @param  array<string, mixed>  $node
     */
    private function type(array $node): ?string
    {
        $type = $node['type'] ?? null;

        if (! is_string($type)) {
            return null;
        }

        $type = trim($type);

        return $type === '' ? null : $type;
    }

    /**
     * An optional stable `id` coerced to string, or null.
     *
     * @param  array<string, mixed>  $node
     */
    private function id(array $node): ?string
    {
        $id = $node['id'] ?? null;

        if (is_string($id) && trim($id) !== '') {
            return trim($id);
        }

        if (is_int($id)) {
            return (string) $id;
        }

        return null;
    }

    /**
     * The node's `data` map (settings/content), always an array.
     *
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function data(array $node): array
    {
        return is_array($node['data'] ?? null) ? $node['data'] : [];
    }
}
