<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Community\Discovery;

/**
 * An immutable, composable discovery query (Phase 26.50D, Rules 6–9).
 *
 * It combines metadata filters (kind, source, category, collection, tags, curated
 * state), a metadata search term (Rule 7), resolution-free compatibility filters
 * (builder version, theme, required dependency — Rule 9), a deterministic sort order
 * (Rule 8) and pagination. All filters are AND-combined and every building method
 * returns a NEW instance; the query is never mutated. Evaluation over the same
 * catalog is deterministic.
 *
 * @see docs/discovery-service.md
 */
final class DiscoveryQuery
{
    public const ALL = 0;

    /**
     * @param  array<int, string>  $tags
     */
    private function __construct(
        public readonly ?string $kind = null,
        public readonly ?string $source = null,
        public readonly ?string $category = null,
        public readonly ?string $collection = null,
        public readonly array $tags = [],
        public readonly ?string $search = null,
        public readonly ?string $state = null,
        public readonly ?string $builderVersion = null,
        public readonly ?string $theme = null,
        public readonly ?string $dependency = null,
        public readonly DiscoverySort $sort = DiscoverySort::Featured,
        public readonly bool $descending = false,
        public readonly int $page = 1,
        public readonly int $perPage = self::ALL,
    ) {}

    public static function make(): self
    {
        return new self();
    }

    public function whereKind(?string $kind): self
    {
        return $this->clone(['kind' => $kind]);
    }

    public function whereSource(?string $source): self
    {
        return $this->clone(['source' => $source]);
    }

    public function whereCategory(?string $category): self
    {
        return $this->clone(['category' => $category]);
    }

    public function inCollection(?string $collection): self
    {
        return $this->clone(['collection' => $collection]);
    }

    public function withTag(string $tag): self
    {
        return $this->clone(['tags' => [...$this->tags, $tag]]);
    }

    /**
     * @param  array<int, string>  $tags
     */
    public function withTags(array $tags): self
    {
        return $this->clone(['tags' => [...$this->tags, ...array_values($tags)]]);
    }

    public function search(?string $term): self
    {
        return $this->clone(['search' => $term]);
    }

    public function inState(?string $state): self
    {
        return $this->clone(['state' => $state]);
    }

    public function compatibleWithBuilder(?string $version): self
    {
        return $this->clone(['builderVersion' => $version]);
    }

    public function compatibleWithTheme(?string $theme): self
    {
        return $this->clone(['theme' => $theme]);
    }

    public function requiresDependency(?string $dependency): self
    {
        return $this->clone(['dependency' => $dependency]);
    }

    public function sortBy(DiscoverySort $sort, bool $descending = false): self
    {
        return $this->clone(['sort' => $sort, 'descending' => $descending]);
    }

    public function paginate(int $page, int $perPage): self
    {
        return $this->clone(['page' => max(1, $page), 'perPage' => max(0, $perPage)]);
    }

    /**
     * Whether the given catalog metadata satisfies every filter (Rule 6). Never
     * inspects payload.
     */
    public function matches(CatalogMetadata $meta): bool
    {
        if ($this->kind !== null && $meta->kind !== $this->kind) {
            return false;
        }
        if ($this->source !== null && $meta->source !== $this->source) {
            return false;
        }
        if ($this->category !== null && $meta->category !== $this->category) {
            return false;
        }
        if ($this->collection !== null && ! $meta->hasCollection($this->collection)) {
            return false;
        }
        foreach ($this->tags as $tag) {
            if (! in_array($tag, $meta->tags, true)) {
                return false;
            }
        }
        if ($this->state !== null && ! $meta->inState($this->state)) {
            return false;
        }
        if ($this->search !== null && ! $meta->matchesSearch($this->search)) {
            return false;
        }
        if ($this->builderVersion !== null && ! $meta->supportsBuilder($this->builderVersion)) {
            return false;
        }
        if ($this->theme !== null && ! $meta->supportsTheme($this->theme)) {
            return false;
        }
        if ($this->dependency !== null && ! $meta->requiresDependency($this->dependency)) {
            return false;
        }

        return true;
    }

    public function isPaginated(): bool
    {
        return $this->perPage > self::ALL;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function clone(array $overrides): self
    {
        return new self(
            array_key_exists('kind', $overrides) ? $overrides['kind'] : $this->kind,
            array_key_exists('source', $overrides) ? $overrides['source'] : $this->source,
            array_key_exists('category', $overrides) ? $overrides['category'] : $this->category,
            array_key_exists('collection', $overrides) ? $overrides['collection'] : $this->collection,
            $overrides['tags'] ?? $this->tags,
            array_key_exists('search', $overrides) ? $overrides['search'] : $this->search,
            array_key_exists('state', $overrides) ? $overrides['state'] : $this->state,
            array_key_exists('builderVersion', $overrides) ? $overrides['builderVersion'] : $this->builderVersion,
            array_key_exists('theme', $overrides) ? $overrides['theme'] : $this->theme,
            array_key_exists('dependency', $overrides) ? $overrides['dependency'] : $this->dependency,
            $overrides['sort'] ?? $this->sort,
            $overrides['descending'] ?? $this->descending,
            $overrides['page'] ?? $this->page,
            $overrides['perPage'] ?? $this->perPage,
        );
    }
}
