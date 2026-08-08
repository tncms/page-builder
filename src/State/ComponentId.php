<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\State;

use Illuminate\Support\Str;

/**
 * Stable component identifiers for the editor state engine (Phase 8).
 *
 * The frontend renderer never needs ids — it renders type + data — so stored
 * layouts may omit them. The editor, however, addresses every component by id
 * (select / hover / move / update). This helper assigns ids to id-less nodes
 * (preserving any that already exist) and regenerates fresh ids for duplicated
 * or pasted subtrees so copies never collide with their source.
 *
 * A node's children live in one of two places depending on its kind:
 *   - a SECTION's children are its `blocks` array
 *   - a BLOCK's children are its `data.blocks` array (container blocks only)
 */
final class ComponentId
{
    public const KIND_SECTION = 'section';

    public const KIND_BLOCK = 'block';

    /** A fresh, collision-resistant component id. */
    public static function generate(): string
    {
        return 'pb-'.Str::lower((string) Str::ulid());
    }

    /**
     * Ensure every section and (nested) block in a normalized payload has an id,
     * keeping ids that are already present. Returns a new payload (no mutation).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function assign(array $payload): array
    {
        $sections = is_array($payload['sections'] ?? null) ? $payload['sections'] : [];

        $payload['sections'] = array_map(
            static fn ($section): array => self::assignNode(
                is_array($section) ? $section : [],
                self::KIND_SECTION,
                false,
            ),
            $sections,
        );

        return $payload;
    }

    /**
     * A deep copy of a node with brand-new ids for it and every descendant.
     *
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    public static function regenerate(array $node, string $kind): array
    {
        return self::assignNode($node, $kind, true);
    }

    /**
     * Ensure a single node (and its descendants) has ids, keeping existing ones.
     *
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    public static function ensureNode(array $node, string $kind): array
    {
        return self::assignNode($node, $kind, false);
    }

    /**
     * Collect the ids of a node and every descendant (used for cycle checks).
     *
     * @param  array<string, mixed>  $node
     * @return array<int, string>
     */
    public static function subtreeIds(array $node, string $kind): array
    {
        $ids = [];
        $id = $node['id'] ?? null;
        if (is_string($id) && $id !== '') {
            $ids[] = $id;
        }

        $children = $kind === self::KIND_SECTION
            ? (is_array($node['blocks'] ?? null) ? $node['blocks'] : [])
            : (is_array($node['data']['blocks'] ?? null) ? $node['data']['blocks'] : []);

        foreach ($children as $child) {
            if (is_array($child)) {
                $ids = [...$ids, ...self::subtreeIds($child, self::KIND_BLOCK)];
            }
        }

        return $ids;
    }

    /**
     * Assign / regenerate ids on a node and recurse into its children.
     *
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private static function assignNode(array $node, string $kind, bool $force): array
    {
        $existing = $node['id'] ?? null;
        if ($force || ! is_string($existing) || trim($existing) === '') {
            $node['id'] = self::generate();
        }

        if ($kind === self::KIND_SECTION) {
            $blocks = is_array($node['blocks'] ?? null) ? $node['blocks'] : [];
            $node['blocks'] = array_map(
                static fn ($block): array => self::assignNode(
                    is_array($block) ? $block : [],
                    self::KIND_BLOCK,
                    $force,
                ),
                $blocks,
            );

            return $node;
        }

        // Block: recurse into nested container children at data.blocks.
        $data = is_array($node['data'] ?? null) ? $node['data'] : [];
        if (is_array($data['blocks'] ?? null)) {
            $data['blocks'] = array_map(
                static fn ($block): array => self::assignNode(
                    is_array($block) ? $block : [],
                    self::KIND_BLOCK,
                    $force,
                ),
                $data['blocks'],
            );
            $node['data'] = $data;
        }

        return $node;
    }
}
