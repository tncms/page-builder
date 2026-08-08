<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Repository;

/**
 * An immutable, composable query over Design Resources.
 *
 * All filters are AND-combined. Tag filters require ALL listed tags to be present.
 * Building methods return new instances (the query is never mutated). Evaluation is
 * deterministic: the same query over the same resource set always yields the same
 * ordered result.
 */
final class ResourceQuery
{
    public const ORDER_RID = 'rid';
    public const ORDER_NAME = 'name';
    public const ORDER_VERSION = 'version';

    /**
     * @param  array<int, string>  $tags
     */
    private function __construct(
        public readonly ?string $kind = null,
        public readonly ?string $source = null,
        public readonly ?string $vendor = null,
        public readonly ?string $category = null,
        public readonly ?string $package = null,
        public readonly ?string $version = null,
        public readonly array $tags = [],
        public readonly ?string $search = null,
        public readonly string $orderBy = self::ORDER_RID,
        public readonly bool $descending = false,
    ) {
    }

    public static function make(): self
    {
        return new self();
    }

    public function whereKind(string $kind): self
    {
        return $this->clone(['kind' => $kind]);
    }

    public function whereSource(string $source): self
    {
        return $this->clone(['source' => $source]);
    }

    public function whereVendor(string $vendor): self
    {
        return $this->clone(['vendor' => $vendor]);
    }

    public function whereCategory(string $category): self
    {
        return $this->clone(['category' => $category]);
    }

    public function wherePackage(string $package): self
    {
        return $this->clone(['package' => $package]);
    }

    public function whereVersion(string $version): self
    {
        return $this->clone(['version' => $version]);
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

    public function search(string $term): self
    {
        return $this->clone(['search' => $term]);
    }

    public function orderBy(string $field, bool $descending = false): self
    {
        return $this->clone(['orderBy' => $field, 'descending' => $descending]);
    }

    public function matches(DesignResource $resource): bool
    {
        if ($this->kind !== null && $resource->kind !== $this->kind) {
            return false;
        }
        if ($this->source !== null && $resource->source !== $this->source) {
            return false;
        }
        if ($this->vendor !== null && ($resource->vendor ?? $resource->source) !== $this->vendor) {
            return false;
        }
        if ($this->category !== null && $resource->category !== $this->category) {
            return false;
        }
        if ($this->version !== null && $resource->version !== $this->version) {
            return false;
        }
        if ($this->package !== null && (($resource->package['packageId'] ?? null) !== $this->package)) {
            return false;
        }
        foreach ($this->tags as $tag) {
            if (! $resource->hasTag($tag)) {
                return false;
            }
        }
        if ($this->search !== null && ! $this->matchesSearch($resource)) {
            return false;
        }

        return true;
    }

    private function matchesSearch(DesignResource $resource): bool
    {
        $needle = mb_strtolower(trim((string) $this->search));
        if ($needle === '') {
            return true;
        }

        $haystacks = [$resource->name, (string) $resource->description, $resource->rid, implode(' ', $resource->tags)];
        foreach ($haystacks as $hay) {
            if (mb_stripos($hay, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function clone(array $overrides): self
    {
        return new self(
            $overrides['kind'] ?? $this->kind,
            $overrides['source'] ?? $this->source,
            $overrides['vendor'] ?? $this->vendor,
            $overrides['category'] ?? $this->category,
            $overrides['package'] ?? $this->package,
            $overrides['version'] ?? $this->version,
            $overrides['tags'] ?? $this->tags,
            $overrides['search'] ?? $this->search,
            $overrides['orderBy'] ?? $this->orderBy,
            $overrides['descending'] ?? $this->descending,
        );
    }
}
