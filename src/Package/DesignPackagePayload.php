<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Package;

use Plugins\PageBuilder\Support\Html;

/**
 * Static utilities for inspecting/normalising a Design Resource payload when it is
 * packaged or imported. Shared by {@see DesignPackageExporter} and
 * {@see DesignPackageImporter} so export and import agree on exactly what a payload
 * contains — no divergent logic.
 *
 * These mirror the primitives the Full Page Import validator already uses
 * (component-type scan, safe-URL check, id stripping); they add NO new runtime.
 */
final class DesignPackagePayload
{
    /** Keys whose string values are treated as asset URLs (must be safe). */
    private const ASSET_KEYS = ['src', 'url', 'href', 'image', 'background', 'logo', 'avatar', 'poster', 'button_url'];

    /** Keys that reference host storage identities — forbidden in a portable package. */
    private const FORBIDDEN_KEYS = ['media_id', 'attachment_id', 'storage_key', 'storage_path', 'file_id', 'disk', 'media', 'attachment'];

    private const UNSAFE = '__unsafe__';

    /**
     * Remove runtime-only node IDs (2-level tree + nested container blocks).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function stripIds(array $payload): array
    {
        $sections = [];
        foreach (($payload['sections'] ?? []) as $section) {
            if (is_array($section)) {
                $sections[] = self::stripNodeId($section);
            }
        }
        $payload['sections'] = $sections;

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private static function stripNodeId(array $node): array
    {
        unset($node['id']);
        if (isset($node['blocks']) && is_array($node['blocks'])) {
            $node['blocks'] = array_map(fn ($b) => is_array($b) ? self::stripNodeId($b) : $b, $node['blocks']);
        }
        if (isset($node['data']['blocks']) && is_array($node['data']['blocks'])) {
            $node['data']['blocks'] = array_map(fn ($b) => is_array($b) ? self::stripNodeId($b) : $b, $node['data']['blocks']);
        }

        return $node;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{sections: array<int, string>, blocks: array<int, string>}
     */
    public static function componentTypes(array $payload): array
    {
        $sectionTypes = [];
        $blockTypes = [];

        foreach (($payload['sections'] ?? []) as $section) {
            if (! is_array($section)) {
                continue;
            }
            if (is_string($section['type'] ?? null)) {
                $sectionTypes[$section['type']] = true;
            }
            self::collectBlockTypes(is_array($section['blocks'] ?? null) ? $section['blocks'] : [], $blockTypes);
        }

        return ['sections' => array_keys($sectionTypes), 'blocks' => array_keys($blockTypes)];
    }

    /**
     * @param  array<int, mixed>  $blocks
     * @param  array<string, bool>  $out
     */
    private static function collectBlockTypes(array $blocks, array &$out): void
    {
        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }
            if (is_string($block['type'] ?? null)) {
                $out[$block['type']] = true;
            }
            $nested = $block['data']['blocks'] ?? null;
            if (is_array($nested)) {
                self::collectBlockTypes($nested, $out);
            }
        }
    }

    /**
     * Asset URLs that fail the safe-URL policy (http/https/relative only).
     *
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    public static function unsafeAssets(array $payload): array
    {
        $unsafe = [];
        self::walk($payload, function (string $key, string $value) use (&$unsafe): void {
            if (in_array($key, self::ASSET_KEYS, true) && $value !== '' && Html::safeUrl($value, self::UNSAFE) === self::UNSAFE) {
                $unsafe[$value] = true;
            }
        });

        return array_keys($unsafe);
    }

    /**
     * Forbidden host-storage references (media_id, attachment_id, storage keys, …).
     *
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    public static function forbiddenReferences(array $payload): array
    {
        $found = [];
        self::walk($payload, function (string $key, string $value) use (&$found): void {
            if (in_array($key, self::FORBIDDEN_KEYS, true) && $value !== '') {
                $found[$key] = true;
            }
        });

        return array_keys($found);
    }

    /**
     * Token references used in the payload (for advisory warnings, not blockers).
     *
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    public static function tokens(array $payload): array
    {
        $tokens = [];
        self::walk($payload, function (string $key, string $value) use (&$tokens): void {
            if (preg_match('/^\{\{\s*token\.([a-z0-9_.\-]+)\s*\}\}$/i', trim($value), $m)) {
                $tokens[$m[1]] = true;
            } elseif (str_starts_with($value, 'token:')) {
                $tokens[substr($value, 6)] = true;
            }
        });

        $list = array_keys($tokens);
        sort($list);

        return $list;
    }

    /**
     * Visit every string value (with its key) in every node's data, recursively.
     * Forbidden keys are visited even when their value is non-string (id ints).
     *
     * @param  array<string, mixed>  $payload
     * @param  callable(string, string): void  $visit
     */
    private static function walk(array $payload, callable $visit): void
    {
        foreach (($payload['sections'] ?? []) as $section) {
            if (is_array($section)) {
                self::walkNode($section, $visit);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  callable(string, string): void  $visit
     */
    private static function walkNode(array $node, callable $visit): void
    {
        self::walkArray(is_array($node['data'] ?? null) ? $node['data'] : [], $visit);

        foreach (($node['blocks'] ?? []) as $block) {
            if (is_array($block)) {
                self::walkNode($block, $visit);
            }
        }
        $nested = $node['data']['blocks'] ?? null;
        if (is_array($nested)) {
            foreach ($nested as $child) {
                if (is_array($child)) {
                    self::walkNode($child, $visit);
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  callable(string, string): void  $visit
     */
    private static function walkArray(array $data, callable $visit): void
    {
        foreach ($data as $key => $value) {
            if (! is_string($key)) {
                continue;
            }
            if (is_string($value)) {
                $visit($key, $value);
            } elseif ((is_int($value) || is_float($value)) && in_array($key, self::FORBIDDEN_KEYS, true)) {
                // A numeric media_id/attachment_id is still a forbidden reference.
                $visit($key, (string) $value);
            } elseif (is_array($value) && $key !== 'blocks') {
                self::walkArray($value, $visit);
            }
        }
    }
}
