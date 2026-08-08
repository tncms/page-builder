<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Capture;

use Plugins\PageBuilder\Package\DesignPackagePayload;
use Plugins\PageBuilder\Registries\BlockRegistry;
use Plugins\PageBuilder\Registries\SectionRegistry;

/**
 * Validates a normalized capture BEFORE persistence (Phase 26.48, Rule 7).
 *
 * Rejects an empty resource, an unsupported root kind, a malformed/broken node, a
 * component with no registered definition, an excessively deep ("recursive")
 * structure, and invalid bindings (forbidden storage refs / unsafe asset URLs).
 * It reuses the package layer's component-type and reference scanners so the
 * capture boundary matches the import boundary exactly.
 */
final class DesignCaptureValidator
{
    /** Depth guard for pathological / recursive-looking structures. */
    public const MAX_DEPTH = 20;

    public function __construct(
        private readonly SectionRegistry $sections,
        private readonly BlockRegistry $blocks,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array{code: string, message: string}>
     */
    public function validate(array $payload, string $kind): array
    {
        $issues = [];

        if ($kind !== 'section' && $kind !== 'block') {
            $issues[] = ['code' => 'unsupported_root', 'message' => 'Only sections and blocks can be saved as a design.'];
        }

        $sections = is_array($payload['sections'] ?? null) ? $payload['sections'] : [];
        if ($sections === []) {
            $issues[] = ['code' => 'empty', 'message' => 'There is nothing to save.'];

            return $issues; // nothing further to check
        }

        if ($this->malformed($payload)) {
            $issues[] = ['code' => 'broken_component', 'message' => 'The selection contains a component without a type.'];
        }

        if ($this->depth($sections) > self::MAX_DEPTH) {
            $issues[] = ['code' => 'recursive', 'message' => 'The selection is nested too deeply.'];
        }

        $missing = $this->missingDefinitions($payload);
        if ($missing !== []) {
            $issues[] = ['code' => 'missing_definition', 'message' => 'Unknown components: '.implode(', ', $missing).'.'];
        }

        if (DesignPackagePayload::forbiddenReferences($payload) !== [] || DesignPackagePayload::unsafeAssets($payload) !== []) {
            $issues[] = ['code' => 'invalid_binding', 'message' => 'The selection contains an unsupported reference or asset.'];
        }

        return $issues;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    private function missingDefinitions(array $payload): array
    {
        $types = DesignPackagePayload::componentTypes($payload);
        $missing = [];

        foreach ($types['sections'] as $type) {
            // The neutral block host section is always valid; it is our own wrapper.
            if ($type !== DesignCaptureNormalizer::HOST_SECTION && ! $this->sections->has($type)) {
                $missing[] = "section:{$type}";
            }
        }
        foreach ($types['blocks'] as $type) {
            if (! $this->blocks->has($type)) {
                $missing[] = "block:{$type}";
            }
        }

        return array_values(array_unique($missing));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function malformed(array $payload): bool
    {
        foreach (DesignPackagePayload::componentTypes($payload)['sections'] as $type) {
            if ($type === '') {
                return true;
            }
        }
        foreach (DesignPackagePayload::componentTypes($payload)['blocks'] as $type) {
            if ($type === '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, mixed>  $sections
     */
    private function depth(array $sections): int
    {
        $max = 0;
        foreach ($sections as $section) {
            if (is_array($section)) {
                $max = max($max, 1 + $this->nodeDepth($section));
            }
        }

        return $max;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function nodeDepth(array $node): int
    {
        $children = [];
        if (is_array($node['blocks'] ?? null)) {
            $children = $node['blocks'];
        } elseif (is_array($node['data']['blocks'] ?? null)) {
            $children = $node['data']['blocks'];
        }

        $max = 0;
        foreach ($children as $child) {
            if (is_array($child)) {
                $max = max($max, 1 + $this->nodeDepth($child));
            }
        }

        return $max;
    }
}
