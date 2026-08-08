<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Contracts;

/**
 * A live component instance in a rendered layout (Phase 7 — Component Runtime).
 *
 * Where a {@see ComponentDefinition} is the static description of a type, an
 * instance is one node in a concrete layout tree: it carries the payload data,
 * runs the resolve lifecycle (defaults → responsive → tokens → dynamic →
 * conditions) into `resolvedData()`, exposes its children, and knows which view
 * + data it renders with.
 *
 * Note: `definition()` returns the normalized definition ARRAY (the shape the
 * registries store), not a {@see ComponentDefinition} object, because the
 * registries are array-based since Phase 6; it is null for unknown types.
 */
interface ComponentInstance
{
    /** Populate the instance from a (normalized) payload node. */
    public function hydrate(array $payload): static;

    /** 'section' or 'block'. */
    public function kind(): string;

    public function type(): string;

    /**
     * The normalized definition array for this type, or null when unknown.
     *
     * @return array<string, mixed>|null
     */
    public function definition(): ?array;

    /** @return array<string, mixed> */
    public function payload(): array;

    /** @return array<string, mixed> */
    public function data(): array;

    /** @return array<string, mixed> */
    public function defaults(): array;

    /** @return array<string, mixed> */
    public function resolvedData(): array;

    /** @return array<string, mixed> */
    public function supports(): array;

    /** @return array<string, mixed> */
    public function assets(): array;

    /** @return array<int, ComponentInstance> */
    public function children(): array;

    public function addChild(ComponentInstance $child): static;

    public function removeChild(ComponentInstance $child): static;

    /** Run the resolve lifecycle (idempotent). */
    public function resolve(): static;

    public function isResolved(): bool;

    public function isVisible(): bool;

    public function setVisible(bool $visible): static;

    /** The Blade view name to render with (''' = none, for unknown blocks). */
    public function renderView(): string;

    /** @return array<string, mixed> The data passed to the view. */
    public function renderData(): array;

    // Lifecycle hook points (overridable; also dispatched via RuntimeHookManager).
    public function beforeResolve(): void;

    public function afterResolve(): void;

    public function beforeRender(): void;

    public function afterRender(): void;
}
