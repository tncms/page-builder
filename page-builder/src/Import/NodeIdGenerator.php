<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Import;

/**
 * Assigns fresh, unique string ids to every node of a (normalized, id-free)
 * LayoutPayload — sections, section-level blocks, and nested composition blocks
 * under data.blocks.
 *
 * A distinct random base per call guarantees a fresh id set on every import, so
 * two imports of the same template never collide and the imported page owns its
 * own ids (copy-on-import). This does not change serialization: ids are ordinary
 * optional string node ids the runtime already understands.
 */
final class NodeIdGenerator
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function assign(array $payload): array
    {
        $base = 'imp'.bin2hex(random_bytes(6));
        $counter = 0;

        $sections = [];
        foreach (($payload['sections'] ?? []) as $section) {
            if (is_array($section)) {
                $sections[] = $this->assignNode($section, $base, $counter, true);
            }
        }

        $payload['sections'] = $sections;

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function assignNode(array $node, string $base, int &$counter, bool $isSection): array
    {
        $node['id'] = $base.'-'.(++$counter);

        if ($isSection) {
            $blocks = [];
            foreach (($node['blocks'] ?? []) as $block) {
                if (is_array($block)) {
                    $blocks[] = $this->assignNode($block, $base, $counter, false);
                }
            }
            $node['blocks'] = $blocks;
        }

        if (isset($node['data']) && is_array($node['data']) && is_array($node['data']['blocks'] ?? null)) {
            $children = [];
            foreach ($node['data']['blocks'] as $child) {
                if (is_array($child)) {
                    $children[] = $this->assignNode($child, $base, $counter, false);
                }
            }
            $node['data']['blocks'] = $children;
        }

        return $node;
    }
}
