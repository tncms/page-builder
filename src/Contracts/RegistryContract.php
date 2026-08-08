<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Contracts;

use Plugins\PageBuilder\Builder\Registry\BuilderResourceProvider;

/**
 * The official **Registry Contract** for Builder Resources (Phase 26.49A, Rule 9).
 *
 * FROZEN. The registry is PRESENTATION-oriented: it aggregates Builder Resources
 * from providers, groups/filters/searches them, and produces the palette metadata
 * the editor boots from. The registry NEVER owns persistence and NEVER owns the
 * Builder runtime. There is exactly ONE registry; no second registry may be
 * introduced.
 *
 * This formalizes the pre-existing {@see \Plugins\PageBuilder\Builder\Registry\BuilderResourceRegistry}
 * public surface (Phase 26.47E) as a named contract; it introduces no new behaviour.
 *
 * @see docs/registry-contract.md
 */
interface RegistryContract
{
    /**
     * Register (or replace) a provider. Idempotent by provider key.
     */
    public function registerProvider(BuilderResourceProvider $provider): void;

    /**
     * Drop the computed palette cache (next read rebuilds).
     */
    public function refresh(): void;

    /**
     * The registered providers, in no guaranteed order.
     *
     * @return array<int, BuilderResourceProvider>
     */
    public function providers(): array;

    /**
     * The grouped palette, cached until the contributed resource set changes.
     *
     * @return array<string, array<int, \Plugins\PageBuilder\Builder\Palette\PaletteGroup>> kind => groups
     */
    public function palette(): array;

    /**
     * The palette as plain arrays for the editor boot metadata.
     *
     * @return array{section: array<int, array<string, mixed>>, block: array<int, array<string, mixed>>}
     */
    public function toEditorArray(): array;

    /**
     * Unified palette search across every contributed source.
     *
     * @return array<int, array<string, mixed>>
     */
    public function search(string $term): array;
}
