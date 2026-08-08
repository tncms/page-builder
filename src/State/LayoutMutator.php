<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\State;

use InvalidArgumentException;
use Plugins\PageBuilder\Support\NestingPolicy;

/**
 * Centralized, safe mutation of normalized layout payloads (Phase 8).
 *
 * Every operation is pure: it takes a payload array, returns a NEW payload, and
 * never mutates its input by reference. This is the single choke point through
 * which the editor (and, later, the Vue command layer) changes a layout, so the
 * structural invariants — sections only at the root, blocks only inside a
 * section or a container block, no cycles — live in exactly one place.
 *
 * Unknown component types are accepted at this level (the renderer skips what it
 * cannot resolve); nesting violations throw so a bad command fails loudly rather
 * than corrupting the tree.
 */
final class LayoutMutator
{
    public function __construct(
        private readonly ComponentLocator $locator = new ComponentLocator(),
    ) {}

    /**
     * Insert a section at the root, at $index (appended when null/out of range).
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $section
     * @return array<string, mixed>
     */
    public function addSection(array $payload, array $section, ?int $index = null): array
    {
        return $this->insertComponent($payload, $section, null, $index);
    }

    /**
     * Insert an existing component (section at root, block inside a parent).
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $component
     * @return array<string, mixed>
     */
    public function insertComponent(array $payload, array $component, ?string $targetParentId, ?int $index = null): array
    {
        if ($targetParentId === null) {
            $section = ComponentId::ensureNode($this->shapeSection($component), ComponentId::KIND_SECTION);
            $sections = $this->childArray($payload, ['sections']);

            return $this->setByPath($payload, ['sections'], $this->spliceIn($sections, $section, $index));
        }

        $parent = $this->locator->find($payload, $targetParentId);
        if ($parent === null) {
            throw new InvalidArgumentException("Cannot insert into unknown parent [{$targetParentId}].");
        }

        // Composition nesting guard (§10). Server-authoritative: even if a UI drop
        // preview mis-fires, an invalid parent/child pairing can never be written.
        $parentType = is_string($parent['node']['type'] ?? null) ? $parent['node']['type'] : '';
        $childType = is_string($component['type'] ?? null) ? $component['type'] : '';
        if ($childType !== '' && ! NestingPolicy::canContain($parentType, $childType)) {
            throw new InvalidArgumentException("A [{$childType}] cannot be placed inside a [{$parentType}].");
        }

        $block = ComponentId::ensureNode($this->shapeBlock($component), ComponentId::KIND_BLOCK);
        $childrenPath = $this->childrenPath($parent);
        $children = $this->childArray($payload, $childrenPath);

        return $this->setByPath($payload, $childrenPath, $this->spliceIn($children, $block, $index));
    }

    /**
     * Remove a component by id. Missing ids are a no-op (payload unchanged).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function removeComponent(array $payload, string $componentId): array
    {
        $found = $this->locator->find($payload, $componentId);
        if ($found === null) {
            return $payload;
        }

        return $this->spliceOut($payload, $found['path']);
    }

    /**
     * Duplicate a component in place (inserted immediately after the original),
     * with fresh ids for the copy and all its descendants.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function duplicateComponent(array $payload, string $componentId): array
    {
        $found = $this->locator->find($payload, $componentId);
        if ($found === null) {
            return $payload;
        }

        $copy = ComponentId::regenerate($found['node'], $found['kind']);

        $siblingsPath = $found['path'];
        $index = array_pop($siblingsPath);
        $siblings = $this->childArray($payload, $siblingsPath);

        return $this->setByPath($payload, $siblingsPath, $this->spliceIn($siblings, $copy, (int) $index + 1));
    }

    /**
     * Re-proportion a container's columns to a preset width list (Phase 26.1).
     *
     * Reconciliation is index-based so existing columns — their ids and their
     * content — survive: a matching column keeps everything but its width, extra
     * preset slots append fresh empty columns, and a width list shorter than the
     * current column count drops the trailing columns (the editor confirms that
     * loss before dispatching). Missing containers are a no-op, like the other
     * id-addressed operations here.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $widths
     * @return array<string, mixed>
     */
    public function applyColumnLayout(array $payload, string $containerId, array $widths): array
    {
        $found = $this->locator->find($payload, $containerId);
        if ($found === null) {
            return $payload;
        }

        $childrenPath = $this->childrenPath($found);
        $existing = array_values($this->childArray($payload, $childrenPath));

        $columns = [];
        foreach (array_values($widths) as $i => $width) {
            $current = $existing[$i] ?? null;
            $column = is_array($current)
                ? $this->withColumnWidth($current, (string) $width)
                : $this->newColumn((string) $width);

            $columns[] = ComponentId::ensureNode($column, ComponentId::KIND_BLOCK);
        }

        return $this->setByPath($payload, $childrenPath, $columns);
    }

    /**
     * Reverse a container's column order (Phase 26.1). A structural sibling
     * reorder — every column rides along with its id and content intact. Missing
     * containers are a no-op, like the other id-addressed operations here.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function reverseColumns(array $payload, string $containerId): array
    {
        $found = $this->locator->find($payload, $containerId);
        if ($found === null) {
            return $payload;
        }

        $childrenPath = $this->childrenPath($found);
        $columns = array_values($this->childArray($payload, $childrenPath));

        return $this->setByPath($payload, $childrenPath, array_reverse($columns));
    }

    /**
     * Return $column with its stored width overridden, leaving id/content intact.
     *
     * @param  array<string, mixed>  $column
     * @return array<string, mixed>
     */
    private function withColumnWidth(array $column, string $width): array
    {
        $data = is_array($column['data'] ?? null) ? $column['data'] : [];
        $data['width'] = $width;
        $column['data'] = $data;

        return $column;
    }

    /**
     * A fresh, empty column node ready to receive elements.
     *
     * @return array<string, mixed>
     */
    private function newColumn(string $width): array
    {
        return [
            'type' => 'column',
            'data' => ['width' => $width, 'blocks' => []],
        ];
    }

    /**
     * Move a component under a new parent (or reorder within the root when the
     * target parent is null and the component is a section).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function moveComponent(array $payload, string $componentId, ?string $targetParentId, int $index): array
    {
        $found = $this->locator->find($payload, $componentId);
        if ($found === null) {
            return $payload;
        }

        $this->assertMoveAllowed($found, $targetParentId);

        $node = $found['node'];

        // Remove first, then re-locate the (id-stable) target in the new payload.
        $payload = $this->spliceOut($payload, $found['path']);

        return $this->insertComponent($payload, $node, $targetParentId, $index);
    }

    /**
     * Shallow-merge new values into a component's `data` map.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateComponentData(array $payload, string $componentId, array $data): array
    {
        $found = $this->locator->find($payload, $componentId);
        if ($found === null) {
            return $payload;
        }

        $current = is_array($found['node']['data'] ?? null) ? $found['node']['data'] : [];

        return $this->setByPath($payload, [...$found['path'], 'data'], array_replace($current, $data));
    }

    /**
     * Replace a component wholesale, preserving its id so selection stays valid.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $componentPayload
     * @return array<string, mixed>
     */
    public function replaceComponent(array $payload, string $componentId, array $componentPayload): array
    {
        $found = $this->locator->find($payload, $componentId);
        if ($found === null) {
            return $payload;
        }

        $replacement = $found['kind'] === ComponentId::KIND_SECTION
            ? $this->shapeSection($componentPayload)
            : $this->shapeBlock($componentPayload);

        $replacement['id'] = $componentId;
        $replacement = ComponentId::ensureNode($replacement, $found['kind']);

        return $this->setByPath($payload, $found['path'], $replacement);
    }

    // ---- Validation -----------------------------------------------------

    /**
     * @param  array{kind: string, id: string, node: array<string, mixed>}  $found
     */
    private function assertMoveAllowed(array $found, ?string $targetParentId): void
    {
        if ($found['kind'] === ComponentId::KIND_SECTION && $targetParentId !== null) {
            throw new InvalidArgumentException('A section can only live at the root.');
        }

        if ($found['kind'] === ComponentId::KIND_BLOCK && $targetParentId === null) {
            throw new InvalidArgumentException('A block cannot be moved to the root.');
        }

        if ($targetParentId !== null) {
            $subtree = ComponentId::subtreeIds($found['node'], $found['kind']);
            if (in_array($targetParentId, $subtree, true)) {
                throw new InvalidArgumentException('A component cannot be moved into itself.');
            }
        }
    }

    // ---- Shaping --------------------------------------------------------

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function shapeSection(array $node): array
    {
        $node['type'] = is_string($node['type'] ?? null) ? $node['type'] : '';
        $node['data'] = is_array($node['data'] ?? null) ? $node['data'] : [];
        $node['blocks'] = is_array($node['blocks'] ?? null) ? $node['blocks'] : [];

        return $node;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function shapeBlock(array $node): array
    {
        $node['type'] = is_string($node['type'] ?? null) ? $node['type'] : '';
        $node['data'] = is_array($node['data'] ?? null) ? $node['data'] : [];
        unset($node['blocks']); // blocks nest under data.blocks, never at the top level

        return $node;
    }

    // ---- Path helpers ---------------------------------------------------

    /**
     * The array-key path to a parent's children array.
     *
     * @param  array{kind: string, path: array<int, int|string>}  $parent
     * @return array<int, int|string>
     */
    private function childrenPath(array $parent): array
    {
        return $parent['kind'] === ComponentId::KIND_SECTION
            ? [...$parent['path'], 'blocks']
            : [...$parent['path'], 'data', 'blocks'];
    }

    /**
     * Insert into a siblings array at $index (append when null / out of range).
     *
     * @param  array<int, mixed>  $siblings
     * @param  array<string, mixed>  $node
     * @return array<int, mixed>
     */
    private function spliceIn(array $siblings, array $node, ?int $index): array
    {
        $siblings = array_values($siblings);
        $count = count($siblings);
        $at = ($index === null || $index < 0 || $index > $count) ? $count : $index;

        array_splice($siblings, $at, 0, [$node]);

        return $siblings;
    }

    /**
     * Remove the node at the given full path (…, siblingsKey, index).
     *
     * @param  array<string, mixed>  $payload
     * @param  array<int, int|string>  $path
     * @return array<string, mixed>
     */
    private function spliceOut(array $payload, array $path): array
    {
        $siblingsPath = $path;
        $index = (int) array_pop($siblingsPath);
        $siblings = array_values($this->childArray($payload, $siblingsPath));

        array_splice($siblings, $index, 1);

        return $this->setByPath($payload, $siblingsPath, $siblings);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, int|string>  $path
     * @return array<int, mixed>
     */
    private function childArray(array $payload, array $path): array
    {
        $value = $this->getByPath($payload, $path);

        return is_array($value) ? $value : [];
    }

    /**
     * @param  array<string, mixed>  $arr
     * @param  array<int, int|string>  $path
     */
    private function getByPath(array $arr, array $path): mixed
    {
        $cursor = $arr;
        foreach ($path as $key) {
            if (! is_array($cursor) || ! array_key_exists($key, $cursor)) {
                return null;
            }
            $cursor = $cursor[$key];
        }

        return $cursor;
    }

    /**
     * Set a value at a path, returning a new array (recursive copy-on-write).
     *
     * @param  array<int|string, mixed>  $arr
     * @param  array<int, int|string>  $path
     * @return array<int|string, mixed>
     */
    private function setByPath(array $arr, array $path, mixed $value): array
    {
        $key = $path[0];

        if (count($path) === 1) {
            $arr[$key] = $value;

            return $arr;
        }

        $child = is_array($arr[$key] ?? null) ? $arr[$key] : [];
        $arr[$key] = $this->setByPath($child, array_slice($path, 1), $value);

        return $arr;
    }
}
