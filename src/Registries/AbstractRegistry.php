<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Registries;

use Plugins\PageBuilder\Contracts\ComponentDefinition;

/**
 * Minimal in-memory registry keyed by a string type.
 *
 * Shared foundation for {@see SectionRegistry} and {@see BlockRegistry}. Stores
 * plain arrays (a `type` key is always guaranteed) so downstream stays simple.
 * Since Phase 6 it also accepts a {@see ComponentDefinition} object or its
 * class-string and flattens it via `toArray()` at registration time — plain
 * array registration keeps its exact previous behaviour for backward compat.
 */
abstract class AbstractRegistry
{
    /**
     * Registered definitions, keyed by type.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $items = [];

    /**
     * Register (or overwrite) a definition. Empty types are ignored. The stored
     * definition is always a plain array carrying its own `type` for convenience
     * downstream — a {@see ComponentDefinition} object or its class-string is
     * flattened via `toArray()` first.
     *
     * @param  array<string, mixed>|ComponentDefinition|class-string  $definition
     */
    public function register(string $type, array|ComponentDefinition|string $definition = []): static
    {
        $type = trim($type);

        if ($type === '') {
            return $this;
        }

        $this->items[$type] = array_merge($this->normalizeDefinition($definition), ['type' => $type]);

        return $this;
    }

    /**
     * Coerce an array / definition object / definition class-string into the
     * plain array shape stored by the registry.
     *
     * @param  array<string, mixed>|ComponentDefinition|string  $definition
     * @return array<string, mixed>
     */
    private function normalizeDefinition(array|ComponentDefinition|string $definition): array
    {
        if (is_string($definition)) {
            $definition = is_a($definition, ComponentDefinition::class, true)
                ? new $definition
                : [];
        }

        if ($definition instanceof ComponentDefinition) {
            return $definition->toArray();
        }

        return $definition;
    }

    /**
     * The definition for a type, or null when it is not registered.
     *
     * @return array<string, mixed>|null
     */
    public function get(string $type): ?array
    {
        return $this->items[$type] ?? null;
    }

    public function has(string $type): bool
    {
        return isset($this->items[$type]);
    }

    /**
     * All registered definitions, keyed by type.
     *
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * Registered type identifiers.
     *
     * @return array<int, string>
     */
    public function types(): array
    {
        return array_keys($this->items);
    }

    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Remove a single registration. Idempotent.
     */
    public function forget(string $type): static
    {
        unset($this->items[$type]);

        return $this;
    }

    /**
     * Drop every registration (primarily for tests and re-seeding).
     */
    public function flush(): static
    {
        $this->items = [];

        return $this;
    }
}
