<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Builder\Registry;

use Plugins\PageBuilder\Builder\Palette\PaletteCategory;
use Plugins\PageBuilder\Builder\Palette\PaletteGroup;
use Plugins\PageBuilder\Builder\Palette\PaletteItem;
use Plugins\PageBuilder\Contracts\RegistryContract;
use Plugins\PageBuilder\Library\Availability\ResourceAvailabilityResolver;
use Plugins\PageBuilder\Support\ResourceMetadataLocalizer;
use Plugins\PageBuilder\Repository\Rid;

/**
 * The single, provider-driven Builder Resource registry (Phase 26.47E).
 *
 * There is exactly ONE registry (Rule 1). Providers (Built-in, Imported, and any
 * third-party or future My Designs / Marketplace / PRO providers) contribute
 * Design Resources pulled from the authoritative repository (Rule 3); the registry
 * owns discovery: grouping by source, categorising, de-duplicating by RID,
 * applying Phase 26.45 availability, and producing the palette metadata the editor
 * boots from. Kinds never mix — section resources register only as sections, block
 * resources only as blocks; kit is reserved (Rules 4/5).
 *
 * The computed palette is cached and only rebuilt when the contributed resource
 * set changes (a package is imported/removed or a resource updated) — never on
 * every Builder load (Rule 13). Insertion is NOT handled here: the editor inserts
 * a resource's id-free payload through the existing insert-pattern / insert-component
 * commands (no second insertion engine, Rules 7/8).
 */
final class BuilderResourceRegistry implements RegistryContract
{
    /** The kinds that become palette elements (kit is reserved). */
    private const PALETTE_KINDS = [Rid::KIND_SECTION, Rid::KIND_BLOCK];

    /** @var array<string, BuilderResourceProvider> keyed by provider key (idempotent). */
    private array $providers = [];

    /**
     * Presentation cache keyed by "adminLocale|fallback" (PB-TEMPLATE-REGISTRY-1-H3). The
     * contributed resource SET is content-derived and locale-invariant (see {@see signature()});
     * only the resolved human labels vary by Admin UI locale, so each locale rendition is cached
     * separately and can never leak another locale's labels.
     *
     * @var array<string, array<string, array<int, PaletteGroup>>>
     */
    private array $cache = [];

    private int $cacheSignature = -1;

    private readonly ResourceMetadataLocalizer $localizer;

    public function __construct(
        private readonly ResourceAvailabilityResolver $availability,
        ?ResourceMetadataLocalizer $localizer = null,
    ) {
        $this->localizer = $localizer ?? new ResourceMetadataLocalizer;
    }

    /**
     * Register (or replace) a provider. Idempotent by key — re-registering the same
     * provider never duplicates it (Rule 6) and invalidates the cache.
     */
    public function registerProvider(BuilderResourceProvider $provider): void
    {
        $this->providers[$provider->key()] = $provider;
        $this->cache = [];
    }

    /** Drop the computed cache (next read rebuilds). */
    public function refresh(): void
    {
        $this->cache = [];
    }

    /**
     * @return array<int, BuilderResourceProvider>
     */
    public function providers(): array
    {
        return array_values($this->providers);
    }

    /**
     * The grouped palette, cached until the contributed resource set changes.
     *
     * @return array<string, array<int, PaletteGroup>> kind => groups
     */
    public function palette(?string $locale = null, ?string $fallback = null): array
    {
        $signature = $this->signature();
        if ($this->cacheSignature !== $signature) {
            // The resource set changed -> drop every cached locale rendition and re-key.
            $this->cache = [];
            $this->cacheSignature = $signature;
        }

        $key = ($locale ?? '').'|'.($fallback ?? '');

        return $this->cache[$key] ??= $this->build($locale, $fallback);
    }

    /**
     * The palette as plain arrays for the editor boot metadata (Rule 10 fields only), with human
     * labels resolved for the given Admin UI locale (PB-TEMPLATE-REGISTRY-1-H3).
     *
     * @return array{section: array<int, array<string, mixed>>, block: array<int, array<string, mixed>>}
     */
    public function toEditorArray(?string $locale = null, ?string $fallback = null): array
    {
        $palette = $this->palette($locale, $fallback);

        return [
            Rid::KIND_SECTION => array_map(
                static fn (PaletteGroup $group): array => $group->toArray(),
                $palette[Rid::KIND_SECTION] ?? [],
            ),
            Rid::KIND_BLOCK => array_map(
                static fn (PaletteGroup $group): array => $group->toArray(),
                $palette[Rid::KIND_BLOCK] ?? [],
            ),
        ];
    }

    /**
     * Unified palette search across built-in AND imported resources (Rule 11).
     * Returns flat item arrays whose name/category/tags/source match the term.
     *
     * @return array<int, array<string, mixed>>
     */
    public function search(string $term, ?string $locale = null, ?string $fallback = null): array
    {
        $term = strtolower(trim($term));
        if ($term === '') {
            return [];
        }

        $matches = [];
        foreach ($this->palette($locale, $fallback) as $groups) {
            foreach ($groups as $group) {
                foreach ($group->categories as $category) {
                    foreach ($category->items as $item) {
                        if (str_contains($item->searchHaystack(), $term)) {
                            $matches[] = $item->toArray();
                        }
                    }
                }
            }
        }

        return $matches;
    }

    /**
     * @return array<string, array<int, PaletteGroup>>
     */
    private function build(?string $locale = null, ?string $fallback = null): array
    {
        // acc[kind][groupKey] = ['label'=>, 'priority'=>, 'cats'=> [catKey => ['label'=>, 'items'=>PaletteItem[]]]]
        $acc = [Rid::KIND_SECTION => [], Rid::KIND_BLOCK => []];
        $seen = [Rid::KIND_SECTION => [], Rid::KIND_BLOCK => []];

        foreach ($this->orderedProviders() as $provider) {
            $groupKey = $provider->group();

            foreach ($provider->resources() as $resource) {
                $kind = $resource->kind;
                if (! in_array($kind, self::PALETTE_KINDS, true)) {
                    continue; // kind isolation — kits never enter the palette (Rule 4)
                }
                if (isset($seen[$kind][$resource->rid])) {
                    continue; // never duplicate a resource (Rule 3)
                }
                $seen[$kind][$resource->rid] = true;

                $availability = $this->availability->resolve($resource);
                if (! $availability->available) {
                    continue; // an unresolved resource is hidden (Rule 9)
                }

                $categoryKey = $resource->category !== '' ? $resource->category : 'general';

                if (! isset($acc[$kind][$groupKey])) {
                    $acc[$kind][$groupKey] = ['label' => $provider->groupLabel(), 'priority' => $provider->priority(), 'cats' => []];
                } else {
                    $acc[$kind][$groupKey]['priority'] = min($acc[$kind][$groupKey]['priority'], $provider->priority());
                }
                if (! isset($acc[$kind][$groupKey]['cats'][$categoryKey])) {
                    $acc[$kind][$groupKey]['cats'][$categoryKey] = ['label' => $this->categoryLabel($categoryKey), 'items' => []];
                }

                $acc[$kind][$groupKey]['cats'][$categoryKey]['items'][] = new PaletteItem(
                    rid: $resource->rid,
                    kind: $kind,
                    // Human label resolved for the Admin UI locale; identity/category/tags stay
                    // invariant (PB-TEMPLATE-REGISTRY-1-H3).
                    name: $this->localizer->name($resource, $locale, $fallback),
                    category: $categoryKey,
                    tags: $resource->tags,
                    source: $resource->source,
                    group: $groupKey,
                    version: $resource->version,
                    compatibility: $resource->compatibility,
                    preview: $resource->preview,
                    available: $availability->available,
                    locked: $availability->locked,
                    requiredCapability: $availability->requiredCapability,
                );
            }
        }

        return [
            Rid::KIND_SECTION => $this->groupsFor($acc[Rid::KIND_SECTION]),
            Rid::KIND_BLOCK => $this->groupsFor($acc[Rid::KIND_BLOCK]),
        ];
    }

    /**
     * @param  array<string, array{label: string, priority: int, cats: array<string, array{label: string, items: array<int, PaletteItem>}>}>  $groups
     * @return array<int, PaletteGroup>
     */
    private function groupsFor(array $groups): array
    {
        $ordered = [];
        foreach ($groups as $key => $group) {
            $ordered[] = [$key, $group];
        }
        // Deterministic: by group priority, then key.
        usort($ordered, static fn (array $a, array $b): int => [$a[1]['priority'], $a[0]] <=> [$b[1]['priority'], $b[0]]);

        $result = [];
        foreach ($ordered as [$key, $group]) {
            $cats = $group['cats'];
            ksort($cats); // categories alphabetical by key
            $categories = [];
            foreach ($cats as $catKey => $cat) {
                $items = $cat['items'];
                usort($items, static fn (PaletteItem $a, PaletteItem $b): int => strcmp($a->name, $b->name));
                $categories[] = new PaletteCategory((string) $catKey, $cat['label'], $items);
            }
            $result[] = new PaletteGroup((string) $key, $group['label'], $categories);
        }

        return $result;
    }

    /**
     * @return array<int, BuilderResourceProvider>
     */
    private function orderedProviders(): array
    {
        $providers = array_values($this->providers);
        usort($providers, static fn (BuilderResourceProvider $a, BuilderResourceProvider $b): int => [$a->priority(), $a->key()] <=> [$b->priority(), $b->key()]);

        return $providers;
    }

    private function signature(): int
    {
        $sum = 0;
        foreach ($this->providers as $provider) {
            $sum = ($sum + $provider->revision() + (int) (crc32($provider->key()) % 100000)) % 2147483647;
        }

        return ($sum + count($this->providers)) % 2147483647;
    }

    private function categoryLabel(string $key): string
    {
        $key = trim($key);
        if ($key === '') {
            return 'General';
        }

        return ucwords(str_replace(['-', '_'], ' ', $key));
    }
}
