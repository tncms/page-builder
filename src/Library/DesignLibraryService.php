<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Library;

use Plugins\PageBuilder\Community\Discovery\CatalogMetadata;
use Plugins\PageBuilder\Community\Discovery\Collection;
use Plugins\PageBuilder\Community\Discovery\CommunityDiscoveryService;
use Plugins\PageBuilder\Community\Discovery\DiscoveryQuery;
use Plugins\PageBuilder\Community\Discovery\DiscoverySort;
use Plugins\PageBuilder\Library\Availability\ResourceAvailabilityResolver;
use Plugins\PageBuilder\Repository\Contracts\DesignResourceRepositoryInterface;
use Plugins\PageBuilder\Repository\DesignResource;
use Plugins\PageBuilder\Repository\ResourceCollection;
use Plugins\PageBuilder\Repository\ResourceQuery;
use Plugins\PageBuilder\Support\CatalogLocale;
use Plugins\PageBuilder\Support\ResourceMetadataLocalizer;
use Throwable;

/**
 * Coordinates the Design Library UI.
 *
 * It is a thin composition layer with NO business logic of its own:
 *   - resources come exclusively from the Design Resource Repository (26.42);
 *   - previews come exclusively from the Runtime Preview Engine route (26.41);
 *   - it never renders HTML, touches the filesystem, or scans registries/packages.
 *
 * Everything it exposes is display metadata plus preview URLs.
 */
final class DesignLibraryService
{
    private readonly ResourceMetadataLocalizer $localizer;

    public function __construct(
        private readonly DesignResourceRepositoryInterface $repository,
        private readonly ResourceAvailabilityResolver $availability,
        private readonly ?CommunityDiscoveryService $discovery = null,
        ?ResourceMetadataLocalizer $localizer = null,
    ) {
        $this->localizer = $localizer ?? new ResourceMetadataLocalizer;
    }

    /**
     * Build a view-model for the library page.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<int, string>    $favorites
     * @return array<string, mixed>
     */
    public function viewModel(array $filters = [], array $favorites = []): array
    {
        $filters = $this->normalizeFilters($filters);

        $cards = $this->cards($this->query($filters), $favorites);

        if (! empty($filters['favorites'])) {
            $cards = array_values(array_filter($cards, static fn (LibraryCard $c): bool => $c->favorite));
        }

        return [
            'cards' => $cards,
            'facets' => $this->facets(),
            'filters' => $filters,
            'count' => count($cards),
            'total' => $this->repository->all()->count(),
        ];
    }

    /**
     * Build the Community **discovery** view-model (Phase 26.50D, Rule 11).
     *
     * The grid is ordered and filtered by the discovery layer (collections, curated
     * states, compatibility, sort), and an additive `community` block carries the
     * collections, featured cards and category facets the Community tab surfaces.
     * Cards are built through the SAME {@see card()} pipeline, so Preview, Insert, Use
     * Template and Availability are unchanged.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<int, string>    $favorites
     * @return array<string, mixed>
     */
    public function communityViewModel(array $filters, array $favorites = []): array
    {
        $discovery = $this->requireDiscovery();
        $base = $this->normalizeFilters($filters);
        $view = [...$base, ...$this->normalizeDiscoveryFilters($filters)];

        $result = $discovery->discover($this->buildDiscoveryQuery($view));

        $favoriteSet = array_fill_keys($favorites, true);
        $cards = array_map(
            fn (CatalogMetadata $meta) => $this->cardForRid($meta->rid, isset($favoriteSet[$meta->rid])),
            $result->items(),
        );
        if (! empty($view['favorites'])) {
            $cards = array_values(array_filter($cards, static fn (LibraryCard $c): bool => $c->favorite));
        }

        return [
            'cards' => $cards,
            'facets' => $this->facets(),
            'filters' => $view,
            'count' => count($cards),
            'total' => $this->repository->all()->count(),
            'community' => [
                'collections' => array_map(static fn (Collection $c): array => $c->toArray(), $discovery->collections()),
                'featured' => array_map(fn (CatalogMetadata $m) => $this->cardForRid($m->rid), $discovery->featured($view['kind'], 6)),
                'categories' => $discovery->categories($view['kind']),
                'states' => CatalogMetadata::STATES,
                'active' => [
                    'collection' => $view['collection'],
                    'state' => $view['state'],
                    'sort' => $view['sort'] ?? DiscoverySort::Featured->value,
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters  normalized (base + discovery) filters
     */
    public function buildDiscoveryQuery(array $filters): DiscoveryQuery
    {
        $query = DiscoveryQuery::make()
            ->whereKind($filters['kind'])
            ->whereCategory($filters['category'])
            ->whereSource($filters['source'])
            ->inCollection($filters['collection'] ?? null)
            ->inState($filters['state'] ?? null)
            ->compatibleWithTheme($filters['theme'] ?? null)
            ->compatibleWithBuilder($filters['builder'] ?? null)
            ->requiresDependency($filters['dependency'] ?? null)
            ->sortBy(DiscoverySort::fromString($filters['sort'] ?? null));

        if ($filters['tag'] !== null) {
            $query = $query->withTag($filters['tag']);
        }
        if ($filters['search'] !== null) {
            $query = $query->search($filters['search']);
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function normalizeDiscoveryFilters(array $filters): array
    {
        return [
            'collection' => $this->cleanString($filters['collection'] ?? null),
            'state' => $this->cleanString($filters['state'] ?? null),
            'sort' => $this->cleanString($filters['sort'] ?? null),
            'theme' => $this->cleanString($filters['theme'] ?? null),
            'builder' => $this->cleanString($filters['builder'] ?? null),
            'dependency' => $this->cleanString($filters['dependency'] ?? null),
        ];
    }

    private function requireDiscovery(): CommunityDiscoveryService
    {
        if ($this->discovery === null) {
            throw new \RuntimeException('Community discovery is not available: no CommunityDiscoveryService was provided.');
        }

        return $this->discovery;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function query(array $filters): ResourceCollection
    {
        return $this->repository->query($this->buildQuery($this->normalizeFilters($filters)));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function buildQuery(array $filters): ResourceQuery
    {
        $query = ResourceQuery::make();

        if ($filters['kind'] !== null) {
            $query = $query->whereKind($filters['kind']);
        }
        if ($filters['category'] !== null) {
            $query = $query->whereCategory($filters['category']);
        }
        if ($filters['source'] !== null) {
            $query = $query->whereSource($filters['source']);
        }
        if ($filters['vendor'] !== null) {
            $query = $query->whereVendor($filters['vendor']);
        }
        if ($filters['tag'] !== null) {
            $query = $query->withTag($filters['tag']);
        }
        if ($filters['search'] !== null) {
            $query = $query->search($filters['search']);
        }

        return $query;
    }

    /**
     * @param  array<int, string>  $favorites
     * @return array<int, LibraryCard>
     */
    public function cards(ResourceCollection $collection, array $favorites = []): array
    {
        $set = array_fill_keys($favorites, true);

        return $collection->map(fn (DesignResource $r): LibraryCard => $this->card($r, isset($set[$r->rid])));
    }

    public function card(DesignResource $resource, bool $favorite = false): LibraryCard
    {
        // Availability is resolved here, NOT in the repository — the repository
        // stays the single source of truth for what exists; entitlement decides
        // only whether the actor may import/insert. Locked resources stay listed
        // and previewable.
        $availability = $this->availability->resolve($resource);

        // Human labels resolved for the Admin UI locale (PB-TEMPLATE-REGISTRY-1-H3) — never the
        // editing locale. Identity/category/tags stay invariant; legacy single-string metadata is
        // returned unchanged.
        $locale = CatalogLocale::admin();
        $fallback = CatalogLocale::fallback();

        return new LibraryCard(
            rid: $resource->rid,
            name: $this->localizer->name($resource, $locale, $fallback),
            kind: $resource->kind,
            vendor: $resource->vendor ?? $resource->source,
            version: $resource->version,
            category: $resource->category,
            tags: $resource->tags,
            previewUrl: $this->previewUrl($resource->rid),
            entitlement: $resource->entitlement(),
            available: $availability->available,
            favorite: $favorite,
            description: $this->localizer->description($resource, $locale, $fallback),
            locked: $availability->locked,
            requiredCapability: $availability->requiredCapability,
            sectionCount: $this->sectionCount($resource),
        );
    }

    /**
     * Number of top-level sections in a resource's payload. Meaningful for pages
     * (shown as "N Sections" on the card); 1 for a single-section resource.
     */
    private function sectionCount(DesignResource $resource): int
    {
        $sections = $resource->payload['sections'] ?? null;

        return is_array($sections) ? count($sections) : 0;
    }

    /**
     * A card for a RID that may no longer resolve (e.g. a stale favorite). Returns
     * an "unavailable" placeholder card when the resource is gone.
     */
    public function cardForRid(string $rid, bool $favorite = false): LibraryCard
    {
        $resource = $this->repository->tryFind($rid);

        if ($resource === null) {
            return new LibraryCard(
                rid: $rid,
                name: $rid,
                kind: 'unknown',
                vendor: 'unknown',
                version: '0.0.0',
                category: 'unavailable',
                tags: [],
                previewUrl: '',
                entitlement: 'free',
                available: false,
                favorite: $favorite,
            );
        }

        return $this->card($resource, $favorite);
    }

    /**
     * Preview URL for a resource — always the Runtime Preview Engine route; the
     * Design Library never renders previews itself.
     */
    public function previewUrl(string $rid, string $device = 'desktop'): string
    {
        $params = ['source' => 'rid', 'ref' => $rid, 'device' => $device];

        try {
            return route('page-builder.preview', $params);
        } catch (Throwable) {
            return url('page-builder/preview').'?'.http_build_query($params);
        }
    }

    /**
     * Filter options derived from the full resource set.
     *
     * @return array<string, array<int, string>>
     */
    public function facets(): array
    {
        $kinds = [];
        $categories = [];
        $sources = [];
        $vendors = [];
        $tags = [];

        foreach ($this->repository->all() as $resource) {
            $kinds[$resource->kind] = true;
            $categories[$resource->category] = true;
            $sources[$resource->source] = true;
            $vendors[$resource->vendor ?? $resource->source] = true;
            foreach ($resource->tags as $tag) {
                $tags[$tag] = true;
            }
        }

        return [
            'kinds' => $this->sortedKeys($kinds),
            'categories' => $this->sortedKeys($categories),
            'sources' => $this->sortedKeys($sources),
            'vendors' => $this->sortedKeys($vendors),
            'tags' => $this->sortedKeys($tags),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function normalizeFilters(array $filters): array
    {
        return [
            'kind' => $this->cleanString($filters['kind'] ?? null),
            'category' => $this->cleanString($filters['category'] ?? null),
            'source' => $this->cleanString($filters['source'] ?? null),
            'vendor' => $this->cleanString($filters['vendor'] ?? null),
            'tag' => $this->cleanString($filters['tag'] ?? null),
            'search' => $this->cleanString($filters['search'] ?? null),
            'favorites' => ! empty($filters['favorites']),
        ];
    }

    private function cleanString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<string, bool>  $set
     * @return array<int, string>
     */
    private function sortedKeys(array $set): array
    {
        $keys = array_keys($set);
        sort($keys);

        return $keys;
    }
}
