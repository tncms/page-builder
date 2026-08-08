<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Community\Discovery;

use Plugins\PageBuilder\Repository\Contracts\DesignResourceRepositoryInterface;
use Plugins\PageBuilder\Repository\DesignResource;

/**
 * The official Community **discovery** service (Phase 26.50D, Rule 6).
 *
 * Discovery is a presentation and indexing layer over the ONE Design Resource
 * Repository — it is NOT another repository (Rule 1). It reads resources from the
 * repository (which already aggregates the Community, Marketplace, Enterprise and PRO
 * providers — Rule 10), overlays additive {@see CatalogMetadata} (collections,
 * featured/recommended/new/updated states, sort weight, display), then queries,
 * filters, sorts and paginates. It NEVER mutates, publishes or synchronizes resources
 * (Rule 6) and it NEVER stores payload (Rule 1).
 *
 * Curation (per-rid overlay + collection descriptors) is injected data, not stored
 * resources: the same service works over any provider's resources, so Marketplace,
 * Enterprise and PRO reuse this discovery layer verbatim (Rule 10).
 *
 * @see docs/community-discovery.md
 * @see docs/discovery-service.md
 */
final class CommunityDiscoveryService
{
    /** @var array<string, array<string, mixed>> rid => curation overlay */
    private readonly array $curation;

    /** @var array<int, Collection> */
    private readonly array $collections;

    /**
     * @param  array<string, array<string, mixed>>  $curation  rid => curation overlay
     * @param  array<int, Collection|array<string, mixed>>  $collections
     */
    public function __construct(
        private readonly DesignResourceRepositoryInterface $repository,
        array $curation = [],
        array $collections = [],
    ) {
        $this->curation = $curation;
        $this->collections = self::normalizeCollections($collections);
    }

    /**
     * Discover resources across the whole repository (all providers — Rule 10).
     */
    public function discover(DiscoveryQuery $query): DiscoveryResult
    {
        return $this->refine($this->repository->all(), $query);
    }

    /**
     * Pure discovery over a given resource set: build catalog metadata, filter, sort
     * and paginate deterministically. Used both by {@see discover()} and by the Design
     * Library to layer discovery on an already-filtered collection.
     *
     * @param  iterable<DesignResource>  $resources
     */
    public function refine(iterable $resources, DiscoveryQuery $query): DiscoveryResult
    {
        $matched = [];
        foreach ($resources as $resource) {
            $meta = $this->catalogFor($resource);
            if ($query->matches($meta)) {
                $matched[] = $meta;
            }
        }

        $comparator = $query->sort->comparator();
        usort($matched, $comparator);
        if ($query->descending) {
            $matched = array_reverse($matched);
        }

        $total = count($matched);

        if ($query->isPaginated()) {
            $offset = ($query->page - 1) * $query->perPage;
            $matched = array_slice($matched, $offset, $query->perPage);
        }

        return new DiscoveryResult(array_values($matched), $total, $query->page, $query->perPage);
    }

    /**
     * The catalog metadata for a resource — its identity/classification plus the
     * curated overlay for its rid (empty when uncurated).
     */
    public function catalogFor(DesignResource $resource): CatalogMetadata
    {
        return CatalogMetadata::fromResource($resource, $this->curationFor($resource->rid));
    }

    /**
     * @return array<string, mixed>
     */
    public function curationFor(string $rid): array
    {
        return $this->curation[$rid] ?? [];
    }

    /**
     * The registered collections, ordered by sort weight then title (Rule 4).
     *
     * @return array<int, Collection>
     */
    public function collections(): array
    {
        $collections = $this->collections;
        usort($collections, static fn (Collection $a, Collection $b): int => ($a->sortWeight <=> $b->sortWeight) ?: strcasecmp($a->title, $b->title));

        return $collections;
    }

    public function collection(string $key): ?Collection
    {
        foreach ($this->collections as $collection) {
            if ($collection->key === $key) {
                return $collection;
            }
        }

        return null;
    }

    /**
     * The distinct categories present in the repository, optionally scoped to a kind,
     * sorted deterministically (Rule 3).
     *
     * @return array<int, string>
     */
    public function categories(?string $kind = null): array
    {
        $categories = [];
        foreach ($this->repository->all() as $resource) {
            if ($kind !== null && $resource->kind !== $kind) {
                continue;
            }
            if ($resource->category !== '') {
                $categories[$resource->category] = true;
            }
        }
        $keys = array_keys($categories);
        sort($keys);

        return $keys;
    }

    /**
     * Featured resources (Rule 5), curated — never algorithmically ranked.
     *
     * @return array<int, CatalogMetadata>
     */
    public function featured(?string $kind = null, int $limit = 0): array
    {
        $query = DiscoveryQuery::make()->inState(CatalogMetadata::STATE_FEATURED)->sortBy(DiscoverySort::Featured);
        if ($kind !== null) {
            $query = $query->whereKind($kind);
        }
        if ($limit > 0) {
            $query = $query->paginate(1, $limit);
        }

        return $this->discover($query)->items();
    }

    /**
     * Discovery facets for a UI: categories, collections, tags and curated states.
     *
     * @return array<string, mixed>
     */
    public function facets(?string $kind = null): array
    {
        $tags = [];
        foreach ($this->repository->all() as $resource) {
            if ($kind !== null && $resource->kind !== $kind) {
                continue;
            }
            foreach ($resource->tags as $tag) {
                $tags[$tag] = true;
            }
        }
        $tagKeys = array_keys($tags);
        sort($tagKeys);

        return [
            'categories' => $this->categories($kind),
            'collections' => array_map(static fn (Collection $c): array => $c->toArray(), $this->collections()),
            'tags' => $tagKeys,
            'states' => CatalogMetadata::STATES,
        ];
    }

    /**
     * @param  array<int, Collection|array<string, mixed>>  $collections
     * @return array<int, Collection>
     */
    private static function normalizeCollections(array $collections): array
    {
        $out = [];
        foreach ($collections as $collection) {
            $collection = $collection instanceof Collection ? $collection : Collection::fromArray($collection);
            if ($collection->isValid()) {
                $out[] = $collection;
            }
        }

        return $out;
    }
}
