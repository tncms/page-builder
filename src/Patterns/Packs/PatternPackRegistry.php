<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Patterns\Packs;

/**
 * Holds the packs discovered during pattern discovery (Phase 25.2, Part 3). On a
 * duplicate pack id the last registration wins (mirrors source priority ordering).
 */
final class PatternPackRegistry
{
    /** @var array<string, PatternPack> */
    private array $packs = [];

    public function register(PatternPack $pack): self
    {
        $this->packs[$pack->id] = $pack;

        return $this;
    }

    public function find(string $id): ?PatternPack
    {
        return $this->packs[$id] ?? null;
    }

    public function has(string $id): bool
    {
        return isset($this->packs[$id]);
    }

    /**
     * @return array<int, PatternPack>
     */
    public function all(): array
    {
        return array_values($this->packs);
    }
}
