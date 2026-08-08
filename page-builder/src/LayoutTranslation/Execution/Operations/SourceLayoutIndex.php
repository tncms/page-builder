<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Execution\Operations;

use Plugins\PageBuilder\LayoutTranslation\Correspondence\NodeCorrespondence;

/**
 * A read-only index of a source layout by correspondence origin (LT-7B3).
 *
 * Layer: Translation (coordinator lowering). Used only while LOWERING accepted plan items into
 * self-contained operations — it reads the source once so the resulting `SyncOperation`s carry the
 * approved node bytes inline (INV-LT-7-OPS-01/02). It mirrors the LT-5 diff indexing (origin → node,
 * parent, sibling index, ordered child origins). This is a coordinator-layer helper; the mutation engine
 * never uses it.
 */
final class SourceLayoutIndex
{
    /**
     * @param  array<string, array{node:array<string,mixed>, parent:?string, index:int, childOrigins:array<int,string>}>  $entries
     */
    private function __construct(private readonly array $entries) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function of(array $payload): self
    {
        $out = [];
        $sections = is_array($payload['sections'] ?? null) ? array_values($payload['sections']) : [];
        foreach ($sections as $i => $section) {
            if (is_array($section)) {
                self::indexNode($section, true, null, $i, $out);
            }
        }

        return new self($out);
    }

    public function has(string $origin): bool
    {
        return isset($this->entries[$origin]);
    }

    /**
     * @return array{node:array<string,mixed>, parent:?string, index:int, childOrigins:array<int,string>}|null
     */
    public function get(string $origin): ?array
    {
        return $this->entries[$origin] ?? null;
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, array{node:array<string,mixed>, parent:?string, index:int, childOrigins:array<int,string>}>  $out
     */
    private static function indexNode(array $node, bool $isSection, ?string $parent, int $index, array &$out): void
    {
        $origin = NodeCorrespondence::originOf($node);

        $children = $isSection
            ? (is_array($node['blocks'] ?? null) ? array_values($node['blocks']) : [])
            : (is_array($node['data']['blocks'] ?? null) ? array_values($node['data']['blocks']) : []);

        $childOrigins = [];
        foreach ($children as $child) {
            if (is_array($child)) {
                $childOrigin = NodeCorrespondence::originOf($child);
                if ($childOrigin !== null) {
                    $childOrigins[] = $childOrigin;
                }
            }
        }

        if ($origin !== null) {
            $out[$origin] = ['node' => $node, 'parent' => $parent, 'index' => $index, 'childOrigins' => $childOrigins];
        }

        foreach ($children as $childIndex => $child) {
            if (is_array($child)) {
                self::indexNode($child, false, $origin, $childIndex, $out);
            }
        }
    }
}
