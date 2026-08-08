<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Diff;

use Plugins\PageBuilder\LayoutTranslation\Correspondence\NodeCorrespondence;
use Plugins\PageBuilder\Package\DesignPackageManifest;

/**
 * The structural diff engine (LT-5, per frozen LT-0-ADR-001 D8).
 *
 * Layer: Translation (diff). Read-only, deterministic, payload-safe: it computes
 * structural change between layouts by matching nodes on their correspondence
 * origin (LT-4), and never mutates, persists, clones or synchronizes. Output is
 * sorted by origin and depends only on the input payloads (same inputs → identical
 * diff).
 *
 * Classification per matched node: Moved (parent/position changed), StructureUpdated
 * (child set/order changed), ContentUpdated (own data changed), else Unchanged
 * (omitted); Added / Removed for unmatched nodes. Style/Setting sub-classification is
 * reserved (needs a field taxonomy).
 */
final class StructuralDiffEngine
{
    /**
     * Two-way diff: the non-Unchanged changes from `$from` to `$to`.
     *
     * @param  array<string, mixed>  $from
     * @param  array<string, mixed>  $to
     */
    public function compare(array $from, array $to): TwoWayDiff
    {
        $fromIndex = $this->index($from);
        $toIndex = $this->index($to);

        $origins = array_keys($fromIndex + $toIndex);
        sort($origins);

        $changes = [];
        foreach ($origins as $origin) {
            $origin = (string) $origin;
            $inFrom = isset($fromIndex[$origin]);
            $inTo = isset($toIndex[$origin]);

            $type = match (true) {
                $inTo && ! $inFrom => NodeChangeType::Added,
                $inFrom && ! $inTo => NodeChangeType::Removed,
                default => $this->classify($fromIndex[$origin], $toIndex[$origin]),
            };

            if ($type !== NodeChangeType::Unchanged) {
                $changes[] = new NodeChange($origin, $type);
            }
        }

        return new TwoWayDiff($changes);
    }

    /**
     * Three-way diff: B→S (source changes), B→T (target changes), and conflicts.
     *
     * @param  array<string, mixed>  $baseline
     * @param  array<string, mixed>  $source
     * @param  array<string, mixed>  $target
     */
    public function threeWay(array $baseline, array $source, array $target): ThreeWayStructuralDiff
    {
        $sourceDiff = $this->compare($baseline, $source);
        $targetDiff = $this->compare($baseline, $target);

        $conflicts = array_values(array_intersect($sourceDiff->changedOrigins(), $targetDiff->changedOrigins()));
        sort($conflicts);

        return new ThreeWayStructuralDiff($sourceDiff, $targetDiff, $conflicts);
    }

    /**
     * @param  array{parent:?string, index:int, children:array<int,?string>, content:string}  $from
     * @param  array{parent:?string, index:int, children:array<int,?string>, content:string}  $to
     */
    private function classify(array $from, array $to): NodeChangeType
    {
        if ($from['parent'] !== $to['parent'] || $from['index'] !== $to['index']) {
            return NodeChangeType::Moved;
        }

        if ($from['children'] !== $to['children']) {
            return NodeChangeType::StructureUpdated;
        }

        if ($from['content'] !== $to['content']) {
            return NodeChangeType::ContentUpdated;
        }

        return NodeChangeType::Unchanged;
    }

    /**
     * Index a payload by correspondence origin.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, array{parent:?string, index:int, children:array<int,?string>, content:string}>
     */
    private function index(array $payload): array
    {
        $out = [];

        $sections = is_array($payload['sections'] ?? null) ? $payload['sections'] : [];
        foreach (array_values($sections) as $i => $section) {
            if (is_array($section)) {
                $this->indexNode($section, true, null, $i, $out);
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, array{parent:?string, index:int, children:array<int,?string>, content:string}>  $out
     */
    private function indexNode(array $node, bool $isSection, ?string $parent, int $index, array &$out): void
    {
        $origin = NodeCorrespondence::originOf($node);

        $children = $isSection
            ? (is_array($node['blocks'] ?? null) ? $node['blocks'] : [])
            : (is_array($node['data']['blocks'] ?? null) ? $node['data']['blocks'] : []);
        $children = array_values($children);

        $childOrigins = array_map(
            static fn ($child): ?string => is_array($child) ? NodeCorrespondence::originOf($child) : null,
            $children,
        );

        if ($origin !== null) {
            $out[$origin] = [
                'parent' => $parent,
                'index' => $index,
                'children' => $childOrigins,
                'content' => $this->contentHash($node),
            ];
        }

        foreach ($children as $childIndex => $child) {
            if (is_array($child)) {
                $this->indexNode($child, false, $origin, $childIndex, $out);
            }
        }
    }

    /**
     * A stable hash of a node's own content (type + data, excluding structural
     * children and correspondence metadata).
     *
     * @param  array<string, mixed>  $node
     */
    private function contentHash(array $node): string
    {
        $data = is_array($node['data'] ?? null) ? $node['data'] : [];
        unset($data['blocks'], $data[NodeCorrespondence::ORIGIN_KEY]);

        return hash('sha256', DesignPackageManifest::canonicalJson([
            'type' => (string) ($node['type'] ?? ''),
            'data' => $data,
        ]));
    }
}
