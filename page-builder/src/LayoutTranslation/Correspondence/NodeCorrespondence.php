<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Correspondence;

/**
 * Structural node correspondence for layout translation (LT-4, decision A1).
 *
 * Layer: Translation (correspondence). Cross-locale node correspondence lives in
 * each node's `data` bag (frozen LT-0-ADR-001 D6 — the normalizer strips unknown
 * top-level node keys but preserves `data`). Two identities coexist: the physical
 * `id` (document-local, may be regenerated) and the stable `data['_origin']`
 * correspondence id.
 *
 * A target node with no `_origin` is TARGET-OWNED (added independently of the
 * source) and must never be overwritten by source synchronization. Stamping only
 * adds `data['_origin']`; it never changes a node's type, physical id, or content,
 * and it is applied to the TARGET only — the source document is never mutated.
 */
final class NodeCorrespondence
{
    public const ORIGIN_KEY = '_origin';

    private const KIND_SECTION = 'section';

    private const KIND_BLOCK = 'block';

    /**
     * Return a copy of the payload with a stable `data['_origin']` stamped on every
     * node that has a physical id and no origin yet. Immutable — the input is not
     * modified.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function stamp(array $payload): array
    {
        $sections = is_array($payload['sections'] ?? null) ? $payload['sections'] : [];

        $payload['sections'] = array_map(
            static fn ($section): array => self::stampNode(is_array($section) ? $section : [], self::KIND_SECTION),
            $sections,
        );

        return $payload;
    }

    /**
     * The correspondence id of a node: its stamped origin, else its physical id,
     * else null (a target-owned node with neither).
     *
     * @param  array<string, mixed>  $node
     */
    public static function originOf(array $node): ?string
    {
        $data = is_array($node['data'] ?? null) ? $node['data'] : [];
        $origin = $data[self::ORIGIN_KEY] ?? null;
        if (is_string($origin) && trim($origin) !== '') {
            return $origin;
        }

        $id = $node['id'] ?? null;

        return is_string($id) && trim($id) !== '' ? $id : null;
    }

    /**
     * A correspondence coverage report over a payload: total nodes, how many carry a
     * stamped origin, how many are target-owned (no origin at all), and any
     * duplicate origins (ambiguous correspondence).
     *
     * @param  array<string, mixed>  $payload
     * @return array{total:int, with_origin:int, target_owned:int, duplicate_origins:array<int,string>}
     */
    public static function report(array $payload): array
    {
        $total = 0;
        $withOrigin = 0;
        $targetOwned = 0;
        $seen = [];
        $duplicates = [];

        foreach (self::flatten($payload) as $node) {
            $total++;
            $data = is_array($node['data'] ?? null) ? $node['data'] : [];
            $stamped = $data[self::ORIGIN_KEY] ?? null;

            if (is_string($stamped) && trim($stamped) !== '') {
                $withOrigin++;
                if (isset($seen[$stamped])) {
                    $duplicates[$stamped] = $stamped;
                }
                $seen[$stamped] = true;
            } elseif (self::originOf($node) === null) {
                $targetOwned++;
            }
        }

        return [
            'total' => $total,
            'with_origin' => $withOrigin,
            'target_owned' => $targetOwned,
            'duplicate_origins' => array_values($duplicates),
        ];
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private static function stampNode(array $node, string $kind): array
    {
        $id = $node['id'] ?? null;
        if (is_string($id) && trim($id) !== '') {
            $data = is_array($node['data'] ?? null) ? $node['data'] : [];
            $existing = $data[self::ORIGIN_KEY] ?? null;
            if (! is_string($existing) || trim($existing) === '') {
                $data[self::ORIGIN_KEY] = $id;
            }
            $node['data'] = $data;
        }

        if ($kind === self::KIND_SECTION) {
            $blocks = is_array($node['blocks'] ?? null) ? $node['blocks'] : [];
            $node['blocks'] = array_map(
                static fn ($block): array => self::stampNode(is_array($block) ? $block : [], self::KIND_BLOCK),
                $blocks,
            );

            return $node;
        }

        $data = is_array($node['data'] ?? null) ? $node['data'] : [];
        if (is_array($data['blocks'] ?? null)) {
            $data['blocks'] = array_map(
                static fn ($block): array => self::stampNode(is_array($block) ? $block : [], self::KIND_BLOCK),
                $data['blocks'],
            );
            $node['data'] = $data;
        }

        return $node;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    private static function flatten(array $payload): array
    {
        $out = [];

        foreach ((is_array($payload['sections'] ?? null) ? $payload['sections'] : []) as $section) {
            if (is_array($section)) {
                self::walk($section, self::KIND_SECTION, $out);
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<int, array<string, mixed>>  $out
     */
    private static function walk(array $node, string $kind, array &$out): void
    {
        $out[] = $node;

        $children = $kind === self::KIND_SECTION
            ? (is_array($node['blocks'] ?? null) ? $node['blocks'] : [])
            : (is_array($node['data']['blocks'] ?? null) ? $node['data']['blocks'] : []);

        foreach ($children as $child) {
            if (is_array($child)) {
                self::walk($child, self::KIND_BLOCK, $out);
            }
        }
    }
}
