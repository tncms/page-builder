<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\State;

/**
 * Read-only addressing over a normalized layout payload (Phase 8).
 *
 * Finds a component by id anywhere in the tree — root sections, section blocks,
 * or blocks nested inside a container block's `data.blocks` — and returns the
 * path metadata the {@see LayoutMutator} needs to splice it safely.
 *
 * The `path` is an array of array keys from the payload root down to the node
 * (e.g. `['sections', 1, 'blocks', 0, 'data', 'blocks', 2]`), so a mutation can
 * locate the exact siblings array by dropping the final index.
 */
final class ComponentLocator
{
    /**
     * Find a component by id, returning its path metadata (and the node itself),
     * or null when the id is not present.
     *
     * @param  array<string, mixed>  $payload
     * @return array{kind: string, id: string, type: string, path: array<int, int|string>, parent_id: string|null, index: int, node: array<string, mixed>}|null
     */
    public function find(array $payload, string $componentId): ?array
    {
        $sections = is_array($payload['sections'] ?? null) ? $payload['sections'] : [];

        return $this->search($sections, ['sections'], ComponentId::KIND_SECTION, null, $componentId);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function exists(array $payload, string $componentId): bool
    {
        return $this->find($payload, $componentId) !== null;
    }

    /**
     * The metadata of the given component's parent, or null when it is a root
     * section (or the component does not exist).
     *
     * @param  array<string, mixed>  $payload
     * @return array{kind: string, id: string, type: string, path: array<int, int|string>, parent_id: string|null, index: int, node: array<string, mixed>}|null
     */
    public function parentOf(array $payload, string $componentId): ?array
    {
        $found = $this->find($payload, $componentId);

        if ($found === null || $found['parent_id'] === null) {
            return null;
        }

        return $this->find($payload, $found['parent_id']);
    }

    /**
     * The array-key path from the payload root to the component, or null.
     *
     * @param  array<string, mixed>  $payload
     * @return array<int, int|string>|null
     */
    public function pathOf(array $payload, string $componentId): ?array
    {
        return $this->find($payload, $componentId)['path'] ?? null;
    }

    /**
     * Depth-first search over a siblings array.
     *
     * @param  array<int, mixed>  $siblings
     * @param  array<int, int|string>  $basePath  path to the siblings array itself
     * @return array{kind: string, id: string, type: string, path: array<int, int|string>, parent_id: string|null, index: int, node: array<string, mixed>}|null
     */
    private function search(array $siblings, array $basePath, string $kind, ?string $parentId, string $componentId): ?array
    {
        foreach ($siblings as $index => $node) {
            if (! is_array($node)) {
                continue;
            }

            $path = [...$basePath, $index];
            $id = is_string($node['id'] ?? null) ? $node['id'] : null;

            if ($id === $componentId) {
                return [
                    'kind' => $kind,
                    'id' => $componentId,
                    'type' => is_string($node['type'] ?? null) ? $node['type'] : '',
                    'path' => $path,
                    'parent_id' => $parentId,
                    'index' => $index,
                    'node' => $node,
                ];
            }

            $child = $this->searchChildren($node, $path, $kind, $id, $componentId);
            if ($child !== null) {
                return $child;
            }
        }

        return null;
    }

    /**
     * Recurse into a node's children (section → blocks, block → data.blocks).
     *
     * @param  array<string, mixed>  $node
     * @param  array<int, int|string>  $path  path to the node
     * @return array{kind: string, id: string, type: string, path: array<int, int|string>, parent_id: string|null, index: int, node: array<string, mixed>}|null
     */
    private function searchChildren(array $node, array $path, string $kind, ?string $nodeId, string $componentId): ?array
    {
        if ($kind === ComponentId::KIND_SECTION) {
            $blocks = is_array($node['blocks'] ?? null) ? $node['blocks'] : [];

            return $this->search($blocks, [...$path, 'blocks'], ComponentId::KIND_BLOCK, $nodeId, $componentId);
        }

        $data = is_array($node['data'] ?? null) ? $node['data'] : [];
        if (! is_array($data['blocks'] ?? null)) {
            return null;
        }

        return $this->search($data['blocks'], [...$path, 'data', 'blocks'], ComponentId::KIND_BLOCK, $nodeId, $componentId);
    }
}
