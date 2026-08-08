<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Patterns;

/**
 * Public entry point for the Pattern Library. Ensures discovery has run (lazily,
 * once) and delegates to the registry. This is what controllers / a facade talk
 * to, so callers never worry about when discovery happens.
 */
final class PatternManager
{
    public function __construct(
        private readonly PatternRegistry $registry,
        private readonly PatternSourceResolver $resolver,
    ) {}

    public function registry(): PatternRegistry
    {
        return $this->ensureDiscovered();
    }

    /**
     * @return array<int, Pattern>
     */
    public function all(): array
    {
        return $this->ensureDiscovered()->all();
    }

    public function find(string $id): ?Pattern
    {
        return $this->ensureDiscovered()->find($id);
    }

    /**
     * @return array<int, Pattern>
     */
    public function featured(): array
    {
        return $this->ensureDiscovered()->featured();
    }

    /**
     * @return array<int, Pattern>
     */
    public function search(string $query): array
    {
        return $this->ensureDiscovered()->search($query);
    }

    /**
     * @param  array<string, mixed>  $criteria
     * @return array<int, Pattern>
     */
    public function filter(array $criteria): array
    {
        return $this->ensureDiscovered()->filter($criteria);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function categories(): array
    {
        return $this->ensureDiscovered()->categories();
    }

    /**
     * The Builder payload for a pattern (lazy). Null when the id is unknown.
     *
     * @return array<string, mixed>|null
     */
    public function payload(string $id): ?array
    {
        return $this->ensureDiscovered()->find($id)?->payload();
    }

    /**
     * Discovered pattern packs (Phase 25.2, Part 3).
     *
     * @return array<int, \Plugins\PageBuilder\Patterns\Packs\PatternPack>
     */
    public function packs(): array
    {
        return $this->ensureDiscovered()->packs();
    }

    public function pack(string $id): ?\Plugins\PageBuilder\Patterns\Packs\PatternPack
    {
        return $this->ensureDiscovered()->pack($id);
    }

    private function ensureDiscovered(): PatternRegistry
    {
        if (! $this->registry->isDiscovered()) {
            $this->registry->discover($this->resolver->resolve());
        }

        return $this->registry;
    }
}
