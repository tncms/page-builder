<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\DataSources;

use Plugins\PageBuilder\DataSources\Exceptions\InvalidDataSourceConfigException;
use Plugins\PageBuilder\Registries\BlockRegistry;
use Plugins\PageBuilder\Registries\SectionRegistry;

/**
 * Save-time gate for data-source configuration in an editor draft (PB-XREG-2).
 *
 * Walks a NORMALIZED payload and, for every node whose registered definition
 * declares `data-source` fields, runs the same {@see DataSourceConfigValidator}
 * the renderer uses. A configuration that is `invalid` against a KNOWN source
 * rejects the whole save (bounded 422). Everything else is preserved verbatim:
 *
 *   - nodes whose component is unregistered (no definition → no schema to judge);
 *   - configurations whose source is unregistered (`unavailable`);
 *   - every built-in component (none declares a data-source field).
 *
 * It never rewrites the payload. Traversal follows the normalizer's shape
 * (sections[].blocks[] with nested `data.blocks`) and is depth-bounded.
 */
final class DataSourceLayoutValidator
{
    private const MAX_DEPTH = 32;

    public function __construct(
        private readonly SectionRegistry $sections,
        private readonly BlockRegistry $blocks,
        private readonly DataSourceConfigValidator $validator,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws InvalidDataSourceConfigException
     */
    public function assertValid(array $payload): void
    {
        foreach (is_array($payload['sections'] ?? null) ? $payload['sections'] : [] as $section) {
            if (! is_array($section)) {
                continue;
            }

            $this->checkNode($section, $this->sections->get((string) ($section['type'] ?? '')));
            $this->checkBlocks($section['blocks'] ?? [], 1);
        }
    }

    private function checkBlocks(mixed $blocks, int $depth): void
    {
        if (! is_array($blocks) || $depth > self::MAX_DEPTH) {
            return;
        }

        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }

            $this->checkNode($block, $this->blocks->get((string) ($block['type'] ?? '')));
            $this->checkBlocks($block['data']['blocks'] ?? null, $depth + 1);
        }
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>|null  $definition
     */
    private function checkNode(array $node, ?array $definition): void
    {
        $fields = DataSourceFields::in($definition);
        if ($fields === []) {
            return;
        }

        $data = is_array($node['data'] ?? null) ? $node['data'] : [];

        foreach ($fields as $name => $accepts) {
            $resolution = $this->validator->inspect($data[$name] ?? null, $accepts);

            if ($resolution->isInvalid()) {
                throw new InvalidDataSourceConfigException(
                    (string) $resolution->reason,
                    is_string($node['id'] ?? null) ? $node['id'] : '',
                    $name,
                );
            }
        }
    }
}
