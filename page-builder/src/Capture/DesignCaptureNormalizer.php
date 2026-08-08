<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Capture;

use Plugins\PageBuilder\Package\DesignPackagePayload;
use Plugins\PageBuilder\Support\LayoutNormalizer;

/**
 * Normalizes a captured Builder selection into a deterministic, id-free,
 * renderable LayoutPayload (Phase 26.48, Rule 6).
 *
 * Editor-only state (runtime/selection ids, hover/drag/history, editor metadata,
 * temporary bindings) never reaches the repository: a captured node carries only
 * {type, data, blocks}, and this strips every id via the existing package
 * stripper. A block is wrapped in a neutral "generic" host section so it is
 * renderable at the document root (mirrors the built-in block resource shape).
 * Output is deterministic for a given input, so the fingerprint is stable.
 */
final class DesignCaptureNormalizer
{
    /** The neutral host-section type used to make a captured block renderable. */
    public const HOST_SECTION = 'generic';

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    public function normalize(array $node, string $kind): array
    {
        $payload = [
            'schema' => LayoutNormalizer::SCHEMA,
            'sections' => [$this->root($node, $kind)],
        ];

        // Strip runtime ids from the section, its blocks, and nested container
        // blocks — the single, shared portable-boundary stripper.
        return DesignPackagePayload::stripIds($payload);
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function root(array $node, string $kind): array
    {
        if ($kind === 'section') {
            return $this->shapeSection($node);
        }

        return [
            'type' => self::HOST_SECTION,
            'data' => [],
            'blocks' => [$this->shapeBlock($node)],
        ];
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function shapeSection(array $node): array
    {
        return [
            'type' => is_string($node['type'] ?? null) ? $node['type'] : '',
            'data' => is_array($node['data'] ?? null) ? $node['data'] : [],
            'blocks' => array_values(array_map(
                fn ($block): array => $this->shapeBlock(is_array($block) ? $block : []),
                is_array($node['blocks'] ?? null) ? $node['blocks'] : [],
            )),
        ];
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function shapeBlock(array $node): array
    {
        $data = is_array($node['data'] ?? null) ? $node['data'] : [];

        // Recurse into nested container blocks at data.blocks (2-level compositions).
        if (isset($data['blocks']) && is_array($data['blocks'])) {
            $data['blocks'] = array_values(array_map(
                fn ($block): array => $this->shapeBlock(is_array($block) ? $block : []),
                $data['blocks'],
            ));
        }

        return [
            'type' => is_string($node['type'] ?? null) ? $node['type'] : '',
            'data' => $data,
        ];
    }
}
