<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Patterns;

use Plugins\PageBuilder\Registries\BlockRegistry;
use Plugins\PageBuilder\Registries\SectionRegistry;

/**
 * Validates patterns (§17). Two tiers, matching the lazy-loading contract (§20):
 *
 *  - discovery-time (cheap): manifest required fields + thumbnail file exists +
 *    payload.json file exists. A pattern that fails this NEVER registers.
 *  - full (on import / insert): the payload structure is well-formed and every
 *    section/block type resolves to a registered component definition.
 */
final class PatternValidator
{
    private const SUPPORTED_SCHEMA_VERSIONS = [1];

    /** Supported MANIFEST schema versions (§10). Older packs default to 1. */
    private const SUPPORTED_MANIFEST_SCHEMAS = [1, 2];

    /** The preview strategies a manifest may declare (§8). */
    private const PREVIEW_TYPES = ['image', 'render', 'generated'];

    public function __construct(
        private readonly SectionRegistry $sections,
        private readonly BlockRegistry $blocks,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, string>
     */
    public function validateManifest(array $data): array
    {
        $errors = [];
        foreach (['id', 'name', 'category'] as $field) {
            if (trim((string) ($data[$field] ?? '')) === '') {
                $errors[] = "manifest.{$field} is required";
            }
        }

        return $errors;
    }

    public function thumbnailExists(Pattern $pattern): bool
    {
        $path = $pattern->thumbnailPath();

        return $path !== '' && is_file($path);
    }

    /**
     * Cheap discovery gate: manifest fields + thumbnail + payload file present.
     *
     * @param  array<string, mixed>  $manifestData
     * @return array<int, string>
     */
    public function validateForDiscovery(string $directory, array $manifestData, PatternLoader $loader): array
    {
        $errors = $this->validateManifest($manifestData);

        $thumb = trim((string) ($manifestData['thumbnail'] ?? 'preview.webp')) ?: 'preview.webp';
        if (! is_file($directory.DIRECTORY_SEPARATOR.$thumb)) {
            $errors[] = "thumbnail [{$thumb}] not found";
        }
        if (! $loader->payloadFileExists($directory)) {
            $errors[] = 'payload.json not found';
        }

        return $errors;
    }

    /**
     * Full payload validation: structure + component-type existence + schema.
     *
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    public function validatePayload(array $payload): array
    {
        $errors = [];

        $version = (int) ($payload['version'] ?? 1);
        if (! in_array($version, self::SUPPORTED_SCHEMA_VERSIONS, true)) {
            $errors[] = "unsupported payload schema version [{$version}]";
        }

        $sections = $payload['sections'] ?? null;
        if (! is_array($sections)) {
            return ['payload.sections must be an array'];
        }

        foreach ($sections as $i => $section) {
            $errors = [...$errors, ...$this->validateSection($section, "sections[{$i}]")];
        }

        return $errors;
    }

    /**
     * @return array<int, string>
     */
    public function validate(Pattern $pattern): array
    {
        $errors = $this->validateManifest($pattern->manifest()->toArray());
        if (! $this->thumbnailExists($pattern)) {
            $errors[] = 'thumbnail not found';
        }

        return [...$errors, ...$this->validatePayload($pattern->payload())];
    }

    /**
     * @param  mixed  $section
     * @return array<int, string>
     */
    private function validateSection(mixed $section, string $path): array
    {
        if (! is_array($section)) {
            return ["{$path} must be an object"];
        }

        $type = (string) ($section['type'] ?? '');
        $errors = [];
        if ($type === '') {
            $errors[] = "{$path}.type is required";
        } elseif (! $this->sections->has($type)) {
            $errors[] = "{$path}: unknown section type [{$type}]";
        }

        foreach ((array) ($section['blocks'] ?? []) as $j => $block) {
            $errors = [...$errors, ...$this->validateBlock($block, "{$path}.blocks[{$j}]")];
        }

        return $errors;
    }

    /**
     * @param  mixed  $block
     * @return array<int, string>
     */
    private function validateBlock(mixed $block, string $path): array
    {
        if (! is_array($block)) {
            return ["{$path} must be an object"];
        }

        $type = (string) ($block['type'] ?? '');
        $errors = [];
        if ($type === '') {
            $errors[] = "{$path}.type is required";
        } elseif (! $this->blocks->has($type)) {
            $errors[] = "{$path}: unknown block type [{$type}]";
        }

        // Blocks may nest either at the top level (legacy columns → column → blocks)
        // or, for composition layout nodes (Container / Column, Phase 25.3), under
        // data.blocks. Accept both so patterns carry legacy AND composition trees.
        $children = [
            ...array_values((array) ($block['blocks'] ?? [])),
            ...array_values((array) ($block['data']['blocks'] ?? [])),
        ];
        foreach ($children as $k => $child) {
            $errors = [...$errors, ...$this->validateBlock($child, "{$path}.blocks[{$k}]")];
        }

        return $errors;
    }

    /**
     * The declared manifest schema is supported (§10). Older packs omit it and
     * default to schema 1.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, string>
     */
    public function validateSchema(array $data): array
    {
        $schema = (int) ($data['schema'] ?? 1);
        if (! in_array($schema, self::SUPPORTED_MANIFEST_SCHEMAS, true)) {
            return ["unsupported manifest schema [{$schema}]"];
        }

        return [];
    }

    /**
     * Preview metadata is well-formed (§8/§15): a known type, and — for `image` — a
     * resolvable source (its own `src` or the manifest thumbnail).
     *
     * @param  array<string, mixed>  $data
     * @return array<int, string>
     */
    public function validatePreview(array $data): array
    {
        $preview = $data['preview'] ?? null;
        if (! is_array($preview)) {
            return [];
        }

        $type = trim((string) ($preview['type'] ?? 'image')) ?: 'image';
        if (! in_array($type, self::PREVIEW_TYPES, true)) {
            return ["invalid preview type [{$type}]"];
        }

        if ($type === 'image') {
            $src = trim((string) ($preview['src'] ?? ''));
            $thumbnail = trim((string) ($data['thumbnail'] ?? ''));
            // A responsive source (desktop/tablet/mobile) also satisfies the requirement (Part 7).
            $hasResponsive = false;
            foreach (['desktop', 'tablet', 'mobile'] as $breakpoint) {
                if (trim((string) ($preview[$breakpoint] ?? '')) !== '') {
                    $hasResponsive = true;
                    break;
                }
            }
            if ($src === '' && $thumbnail === '' && ! $hasResponsive) {
                return ['image preview requires a preview.src, a responsive source, or a manifest thumbnail'];
            }
        }

        return [];
    }

    /**
     * Variable declarations are well-formed (§15). Duplicate ids are fatal; a missing
     * id is fatal.
     *
     * @param  mixed  $variables
     * @return array<int, string>
     */
    public function validateVariables(mixed $variables): array
    {
        if (! is_array($variables)) {
            return [];
        }

        $errors = [];
        $seen = [];
        foreach ($variables as $i => $variable) {
            if (! is_array($variable)) {
                $errors[] = "variables[{$i}] must be an object";

                continue;
            }
            $id = trim((string) ($variable['id'] ?? ''));
            if ($id === '') {
                $errors[] = "variables[{$i}].id is required";

                continue;
            }
            if (isset($seen[$id])) {
                $errors[] = "duplicate variable id [{$id}]";
            }
            $seen[$id] = true;
        }

        return $errors;
    }

    /**
     * Cross-check placeholders in the payload against declared variables (§15). Every
     * `{{id}}` MUST resolve to a declared variable (unknown placeholder → fatal); a
     * declared-but-unreferenced variable is a warning only.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $variableIds
     * @return array{errors: array<int, string>, warnings: array<int, string>}
     */
    public function validatePlaceholders(array $payload, array $variableIds): array
    {
        $used = (new PatternVariableResolver())->placeholders($payload);

        $errors = [];
        foreach ($used as $id) {
            if (! in_array($id, $variableIds, true)) {
                $errors[] = "unknown placeholder [{{$id}}] has no matching variable";
            }
        }

        $warnings = [];
        foreach ($variableIds as $id) {
            if (! in_array($id, $used, true)) {
                $warnings[] = "variable [{$id}] is never used in the payload";
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Full bundle validation used on import / explicit validate (§15). Combines every
     * check and separates fatal errors from warnings. Warnings never block import.
     *
     * @param  array<string, mixed>  $manifestData
     * @param  array<string, mixed>  $payload
     * @return array{errors: array<int, string>, warnings: array<int, string>}
     */
    public function validateBundle(array $manifestData, array $payload): array
    {
        $errors = [
            ...$this->validateManifest($manifestData),
            ...$this->validateSchema($manifestData),
            ...$this->validatePreview($manifestData),
            ...$this->validateVariables($manifestData['variables'] ?? []),
            ...$this->validatePayload($payload),
        ];

        $variableIds = $this->declaredVariableIds($manifestData['variables'] ?? []);
        $placeholders = $this->validatePlaceholders($payload, $variableIds);

        return [
            'errors' => [...$errors, ...$placeholders['errors']],
            'warnings' => $placeholders['warnings'],
        ];
    }

    /**
     * @param  mixed  $variables
     * @return array<int, string>
     */
    private function declaredVariableIds(mixed $variables): array
    {
        if (! is_array($variables)) {
            return [];
        }

        $ids = [];
        foreach ($variables as $variable) {
            if (is_array($variable)) {
                $id = trim((string) ($variable['id'] ?? ''));
                if ($id !== '') {
                    $ids[] = $id;
                }
            }
        }

        return $ids;
    }
}
