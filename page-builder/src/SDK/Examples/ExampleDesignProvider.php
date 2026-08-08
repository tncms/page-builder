<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\SDK\Examples;

use Plugins\PageBuilder\Repository\Rid;
use Plugins\PageBuilder\SDK\AbstractDesignProvider;
use Plugins\PageBuilder\Support\LayoutNormalizer;

/**
 * The official REFERENCE Design Resource provider (Phase 26.49B, Rule 2) —
 * documentation-by-code for building a channel entirely through the published SDK.
 *
 * It demonstrates every Rule 6 capability without touching the Repository, the
 * Registry, the Capture Pipeline or Runtime Preview:
 *
 *   - list        — {@see provideResources()} yields immutable resources
 *   - find        — inherited uniform RID resolution
 *   - search      — resources are queryable/searchable by the repository
 *   - filtering   — kind / category / tag filters work out of the box
 *   - preview     — every resource is RID-resolvable, so Runtime Preview renders it
 *   - availability — a free resource and a capability-gated (PRO-style) resource
 *   - metadata    — the immutable DesignResource metadata projection
 *
 * A real channel (Community, Marketplace, PRO, Portable Package) replaces the
 * static catalog below with its own source (registry, package files, network,
 * database) — the surface it presents stays identical.
 *
 * This lives under `src/` so the plugin PSR-4 loader autoloads it (the plugin has a
 * single `src/` autoload root); it is reference material, not wired into boot.
 *
 * @see docs/provider-sdk.md
 */
final class ExampleDesignProvider extends AbstractDesignProvider
{
    public const KEY = 'example';

    public function key(): string
    {
        return self::KEY;
    }

    /**
     * A remote/backed channel would override with a cheap stored token; a static
     * catalog can pin a constant. Kept explicit here to show the seam.
     */
    public function revision(): int
    {
        return 1;
    }

    protected function provideResources(): iterable
    {
        yield $this->makeResource(
            kind: Rid::KIND_SECTION,
            resourceId: 'welcome-hero',
            name: 'Welcome Hero',
            category: 'hero',
            payload: $this->section('example-hero', [
                'heading' => 'Build with the Provider SDK',
                'subheading' => 'A reference resource served by ExampleDesignProvider.',
            ]),
            tags: ['example', 'hero'],
            description: 'A hero section demonstrating a free, freely-available resource.',
        );

        yield $this->makeResource(
            kind: Rid::KIND_BLOCK,
            resourceId: 'call-to-action',
            name: 'Call To Action',
            category: 'content',
            payload: $this->blockInSection('example-cta', [
                'label' => 'Get started',
            ]),
            tags: ['example', 'cta'],
            description: 'A block wrapped in a neutral section so it renders at document root.',
        );

        // A capability-gated resource: still listed and previewable, but the
        // availability resolver locks import/insert behind a capability (C8). This
        // shows the FREE/PRO boundary without introducing any PRO feature.
        yield $this->makeResource(
            kind: Rid::KIND_PAGE,
            resourceId: 'landing-starter',
            name: 'Landing Starter',
            category: 'landing',
            payload: $this->page(),
            tags: ['example', 'landing', 'starter'],
            description: 'A full-page starter gated behind a capability to demonstrate availability.',
            distribution: [
                'entitlement' => 'pro',
                'requiredCapability' => 'page-builder.pro',
            ],
        );
    }

    /**
     * A single-section LayoutPayload (id-free, renderable).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function section(string $type, array $data): array
    {
        return [
            'schema' => LayoutNormalizer::SCHEMA,
            'sections' => [[
                'type' => $type,
                'id' => null,
                'data' => $data,
                'blocks' => [],
            ]],
        ];
    }

    /**
     * A block wrapped in a neutral section (a block cannot live at document root).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function blockInSection(string $blockType, array $data): array
    {
        return [
            'schema' => LayoutNormalizer::SCHEMA,
            'sections' => [[
                'type' => 'generic',
                'id' => null,
                'data' => [],
                'blocks' => [[
                    'type' => $blockType,
                    'id' => null,
                    'data' => $data,
                ]],
            ]],
        ];
    }

    /**
     * A two-section page LayoutPayload.
     *
     * @return array<string, mixed>
     */
    private function page(): array
    {
        return [
            'schema' => LayoutNormalizer::SCHEMA,
            'sections' => [
                [
                    'type' => 'example-hero',
                    'id' => null,
                    'data' => ['heading' => 'Landing Starter'],
                    'blocks' => [],
                ],
                [
                    'type' => 'generic',
                    'id' => null,
                    'data' => [],
                    'blocks' => [[
                        'type' => 'example-cta',
                        'id' => null,
                        'data' => ['label' => 'Start now'],
                    ]],
                ],
            ],
        ];
    }
}
