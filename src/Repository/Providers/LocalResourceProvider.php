<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Repository\Providers;

use Plugins\PageBuilder\Contracts\DesignResourceProvider;
use Plugins\PageBuilder\Registries\BlockRegistry;
use Plugins\PageBuilder\Registries\SectionRegistry;
use Plugins\PageBuilder\Repository\DesignResource;
use Plugins\PageBuilder\Repository\Exceptions\InvalidResourceIdentifierException;
use Plugins\PageBuilder\Repository\Rid;
use Plugins\PageBuilder\Support\LayoutNormalizer;
use Plugins\PageBuilder\Support\SampleLayout;

/**
 * Exposes the builder's built-in resources as Design Resources:
 *   - every registered section  → builtin:section:<type>
 *   - every registered block     → builtin:block:<type>
 *   - a ready sample page        → builtin:page:sample
 *
 * This is the infrastructure that reads the SectionRegistry/BlockRegistry so that
 * consumers never have to. Payloads are materialised in renderable (id-free)
 * LayoutPayload form, so preview/apply can consume them directly.
 */
final class LocalResourceProvider implements DesignResourceProvider
{
    public const KEY = 'local';
    public const SOURCE = 'builtin';
    public const SAMPLE_ID = 'sample';

    public function __construct(
        private readonly SectionRegistry $sections,
        private readonly BlockRegistry $blocks,
    ) {
    }

    public function key(): string
    {
        return self::KEY;
    }

    public function priority(): int
    {
        // Builtin resources are the lowest-precedence fallback: imported/premium
        // providers (lower numbers) may take precedence for their own sources.
        return 100;
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function revision(): int
    {
        return $this->sections->count() * 100003 + $this->blocks->count();
    }

    public function all(): iterable
    {
        yield $this->samplePage();

        foreach ($this->sections->types() as $type) {
            yield $this->section((string) $type);
        }

        foreach ($this->blocks->types() as $type) {
            yield $this->block((string) $type);
        }
    }

    public function find(string $rid): ?DesignResource
    {
        try {
            $parsed = Rid::parse($rid);
        } catch (InvalidResourceIdentifierException) {
            return null;
        }

        if ($parsed->source !== self::SOURCE) {
            return null;
        }

        return match ($parsed->kind) {
            Rid::KIND_PAGE => $parsed->resourceId === self::SAMPLE_ID ? $this->samplePage() : null,
            Rid::KIND_SECTION => $this->sections->has($parsed->resourceId) ? $this->section($parsed->resourceId) : null,
            Rid::KIND_BLOCK => $this->blocks->has($parsed->resourceId) ? $this->block($parsed->resourceId) : null,
            default => null,
        };
    }

    private function samplePage(): DesignResource
    {
        return new DesignResource(
            rid: self::SOURCE.':'.Rid::KIND_PAGE.':'.self::SAMPLE_ID,
            source: self::SOURCE,
            kind: Rid::KIND_PAGE,
            resourceId: self::SAMPLE_ID,
            version: '1.0.0',
            name: 'Sample Page',
            category: 'starter',
            tags: ['sample', 'starter'],
            payload: SampleLayout::make(),
            vendor: self::SOURCE,
            description: 'A ready-made sample page assembled from built-in components.',
            compatibility: ['schemaVersion' => '1'],
            distribution: ['entitlement' => 'free'],
        );
    }

    private function section(string $type): DesignResource
    {
        $definition = $this->sections->get($type) ?? [];

        $payload = [
            'schema' => LayoutNormalizer::SCHEMA,
            'sections' => [[
                'type' => $type,
                'id' => null,
                'data' => $this->defaults($definition),
                'blocks' => [],
            ]],
        ];

        return new DesignResource(
            rid: self::SOURCE.':'.Rid::KIND_SECTION.':'.$type,
            source: self::SOURCE,
            kind: Rid::KIND_SECTION,
            resourceId: $type,
            version: '1.0.0',
            name: $this->label($definition, $type),
            category: $this->category($definition, 'section'),
            payload: $payload,
            vendor: self::SOURCE,
            compatibility: ['schemaVersion' => '1'],
            distribution: ['entitlement' => 'free'],
        );
    }

    private function block(string $type): DesignResource
    {
        $definition = $this->blocks->get($type) ?? [];

        // A block cannot live at document root (2-level frozen hierarchy); wrap it
        // in a neutral "generic" section so it is renderable.
        $payload = [
            'schema' => LayoutNormalizer::SCHEMA,
            'sections' => [[
                'type' => 'generic',
                'id' => null,
                'data' => [],
                'blocks' => [[
                    'type' => $type,
                    'id' => null,
                    'data' => $this->defaults($definition),
                ]],
            ]],
        ];

        return new DesignResource(
            rid: self::SOURCE.':'.Rid::KIND_BLOCK.':'.$type,
            source: self::SOURCE,
            kind: Rid::KIND_BLOCK,
            resourceId: $type,
            version: '1.0.0',
            name: $this->label($definition, $type),
            category: $this->category($definition, 'block'),
            payload: $payload,
            vendor: self::SOURCE,
            compatibility: ['schemaVersion' => '1'],
            distribution: ['entitlement' => 'free'],
        );
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    private function defaults(array $definition): array
    {
        return is_array($definition['defaults'] ?? null) ? $definition['defaults'] : [];
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function label(array $definition, string $fallback): string
    {
        $label = $definition['label'] ?? null;

        return is_string($label) && $label !== '' ? $label : ucfirst(str_replace('-', ' ', $fallback));
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function category(array $definition, string $fallback): string
    {
        $category = $definition['category'] ?? null;

        return is_string($category) && $category !== '' ? $category : $fallback;
    }
}
