<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Catalog;

use InvalidArgumentException;
use Plugins\PageBuilder\Components\ComponentAvailability;
use Plugins\PageBuilder\Components\ComponentProviderRegistry;
use Plugins\PageBuilder\DataSources\DataSourceProviderRegistry;
use Plugins\PageBuilder\Origins\OriginCapabilityPolicy;
use Plugins\PageBuilder\Origins\ProviderOrigin;
use Plugins\PageBuilder\Registries\BlockRegistry;
use Plugins\PageBuilder\Registries\SectionRegistry;
use Plugins\PageBuilder\Repository\DesignResource;
use Plugins\PageBuilder\Repository\DesignResourceRepository;
use Plugins\PageBuilder\Repository\Theme\ActiveThemeResourceLocator;
use Plugins\PageBuilder\Repository\Theme\ThemeResourceReader;
use Plugins\PageBuilder\Support\CatalogLocale;
use Plugins\PageBuilder\Support\RenderLocale;
use Throwable;

/**
 * The ONE read-only discovery surface across Page Builder's specialized registries (PB-XREG-4): components,
 * interactive behaviors, data sources, and design resources, from built-ins, active plugins, and the active theme.
 *
 * It orchestrates and describes — it never stores, validates, registers or renders. Save and render authority stay
 * with the registry that owns each item. Records are bounded public metadata only: no provider objects,
 * callables, class names, filesystem paths, or view paths. Order is deterministic (type, then id).
 *
 * Components and data sources are described only once their registries are frozen (the boot boundary), so a
 * catalog read can never observe a half-registered state.
 */
final class UnifiedCatalog
{
    public const VERSION = '1';

    public const TYPE_COMPONENT = 'component';

    public const TYPE_BEHAVIOR = 'behavior';

    public const TYPE_DATA_SOURCE = 'data-source';

    public const TYPE_RESOURCE = 'resource';

    public const TYPES = [self::TYPE_COMPONENT, self::TYPE_BEHAVIOR, self::TYPE_DATA_SOURCE, self::TYPE_RESOURCE];

    private const FILTERS = ['type', 'origin', 'slug'];

    /** @var list<array<string, mixed>>|null components + behaviors + data sources (frozen → stable) */
    private ?array $registered = null;

    public function __construct(
        private readonly ComponentProviderRegistry $components,
        private readonly SectionRegistry $sections,
        private readonly BlockRegistry $blocks,
        private readonly DataSourceProviderRegistry $dataSources,
        private readonly ?DesignResourceRepository $resources = null,
        private readonly ?ActiveThemeResourceLocator $themeResources = null,
    ) {}

    public function isReady(): bool
    {
        return $this->components->isFrozen() && $this->dataSources->isFrozen();
    }

    /**
     * @param  array<string, mixed>  $filters  `type` / `origin` (string or list), `slug` (string)
     * @return list<array<string, mixed>>
     *
     * @throws CatalogNotReadyException before the registries froze
     * @throws InvalidArgumentException for an unknown or malformed filter
     */
    public function records(array $filters = []): array
    {
        [$types, $origins, $slug] = $this->filters($filters);

        if (! $this->isReady()) {
            throw new CatalogNotReadyException('The Page Builder catalog is available after boot.');
        }

        $this->registered ??= $this->describeRegistered();

        $records = $this->registered;
        if ($types === [] || in_array(self::TYPE_RESOURCE, $types, true)) {
            $records = [...$records, ...$this->describeResources()];
        }

        $records = array_values(array_filter($records, static fn (array $r): bool => ($types === [] || in_array($r['type'], $types, true))
            && ($origins === [] || in_array($r['origin']['kind'], $origins, true))
            && ($slug === null || $r['origin']['slug'] === $slug)));

        usort($records, static fn (array $a, array $b): int => [$a['type'], $a['id']] <=> [$b['type'], $b['id']]);

        return $records;
    }

    /** @param  array<string, mixed>  $filters */
    public function snapshotHash(array $filters = []): string
    {
        return hash('sha256', (string) json_encode($this->records($filters)));
    }

    /** The truthful state of a stored component or data-source identifier (KNOWN / UNAVAILABLE / UNKNOWN). */
    public function availability(string $id): string
    {
        $component = $this->components->availability($id);
        if ($component === ComponentAvailability::KNOWN) {
            return $component;
        }

        return $this->dataSources->availability($id) === ComponentAvailability::KNOWN ? ComponentAvailability::KNOWN : $component;
    }

    /** @return array{0: list<string>, 1: list<string>, 2: ?string} */
    private function filters(array $filters): array
    {
        foreach (array_keys($filters) as $key) {
            if (! in_array($key, self::FILTERS, true)) {
                throw new InvalidArgumentException('Unknown catalog filter.');
            }
        }

        $types = $this->listFilter($filters['type'] ?? [], self::TYPES);
        $origins = $this->listFilter($filters['origin'] ?? [], ProviderOrigin::KINDS);

        $slug = $filters['slug'] ?? null;
        if ($slug !== null && (! is_string($slug) || strlen($slug) > 64 || preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug) !== 1)) {
            throw new InvalidArgumentException('Invalid catalog slug filter.');
        }

        return [$types, $origins, $slug];
    }

    /**
     * @param  list<string>  $allowed
     * @return list<string>
     */
    private function listFilter(mixed $value, array $allowed): array
    {
        $values = is_string($value) ? [$value] : $value;

        if (! is_array($values) || count($values) > count($allowed)) {
            throw new InvalidArgumentException('Invalid catalog filter.');
        }

        foreach ($values as $item) {
            if (! is_string($item) || ! in_array($item, $allowed, true)) {
                throw new InvalidArgumentException('Invalid catalog filter.');
            }
        }

        return array_values(array_unique($values));
    }

    /** @return list<array<string, mixed>> */
    private function describeRegistered(): array
    {
        $records = [];
        $snapshot = $this->components->snapshot();
        $locale = CatalogLocale::admin();
        $fallback = CatalogLocale::fallback();

        foreach (['section' => $this->sections->all(), 'block' => $this->blocks->all()] as $kind => $definitions) {
            foreach ($definitions as $definition) {
                $id = (string) ($definition['type'] ?? '');
                $origin = $this->components->originOf($id);
                if ($origin === null) {
                    continue; // raw, non-provider registrations are outside the provider authority
                }

                $behavior = $this->components->behaviorFor($id);
                $dataFields = $this->dataSourceFields($definition['schema'] ?? null);
                $capabilities = ['insert'];
                if ($behavior !== null) {
                    $capabilities[] = 'interactive';
                }
                if ($dataFields !== []) {
                    $capabilities[] = 'data-bound';
                }

                $records[] = [
                    'type' => self::TYPE_COMPONENT,
                    'id' => $id,
                    'kind' => $kind,
                    'origin' => $origin->toArray(),
                    'version' => (string) ($snapshot[$id]['version'] ?? ''),
                    'availability' => ComponentAvailability::KNOWN,
                    'label' => $this->label($definition, $locale, $fallback),
                    'category' => (string) ($definition['category'] ?? ''),
                    'icon' => is_string($definition['icon'] ?? null) ? $definition['icon'] : null,
                    'capabilities' => $capabilities,
                    'relations' => array_filter([
                        'behavior' => $behavior?->id(),
                        'dataSourceFields' => $dataFields === [] ? null : $dataFields,
                    ], static fn ($v): bool => $v !== null),
                ];

                if ($behavior !== null) {
                    $records[$behavior->id().'@behavior'] ??= [
                        'type' => self::TYPE_BEHAVIOR,
                        'id' => $behavior->id(),
                        'kind' => null,
                        'origin' => $origin->toArray(),
                        'version' => $behavior->version(),
                        'availability' => ComponentAvailability::KNOWN,
                        'label' => $behavior->id(),
                        'category' => '',
                        'icon' => null,
                        'capabilities' => $behavior->contexts(),
                        'relations' => [],
                    ];
                }
            }
        }

        foreach ($this->dataSources->snapshot() as $id => $entry) {
            $records[] = [
                'type' => self::TYPE_DATA_SOURCE,
                'id' => (string) $id,
                'kind' => (string) $entry['recordKind'],
                'origin' => ProviderOrigin::plugin((string) $entry['namespace'])->toArray(),
                'version' => (string) $entry['version'],
                'availability' => ComponentAvailability::KNOWN,
                'label' => $this->dataSourceLabel((string) $id, $locale),
                'category' => '',
                'icon' => null,
                'capabilities' => [OriginCapabilityPolicy::DATA_SOURCES],
                'relations' => ['maxLimit' => (int) $entry['maxLimit'], 'defaultLimit' => (int) $entry['defaultLimit']],
            ];
        }

        return array_values($records);
    }

    /** @return list<array<string, mixed>> */
    private function describeResources(): array
    {
        if ($this->resources === null) {
            return [];
        }

        $theme = $this->themeResources?->activeThemeSlug();
        $records = [];

        try {
            foreach ($this->resources->all() as $resource) {
                if (! $resource instanceof DesignResource || ! $resource->browsable) {
                    continue;
                }

                $origin = $resource->source === ThemeResourceReader::SOURCE
                    ? ($theme !== null ? ProviderOrigin::theme($theme) : null)
                    : ProviderOrigin::builtin();
                if ($origin === null) {
                    continue;
                }

                $records[] = [
                    'type' => self::TYPE_RESOURCE,
                    'id' => $resource->rid,
                    'kind' => $resource->kind,
                    'origin' => $origin->toArray(),
                    'version' => $resource->version,
                    'availability' => ComponentAvailability::KNOWN,
                    'label' => $resource->name,
                    'category' => $resource->category,
                    'icon' => null,
                    'capabilities' => ['insert'],
                    'relations' => [],
                ];
            }
        } catch (Throwable) {
            return [];
        }

        return $records;
    }

    /** @return list<string> the names of `data-source` fields in a definition schema */
    private function dataSourceFields(mixed $schema): array
    {
        $names = [];
        foreach ((array) (is_array($schema) ? ($schema['tabs'] ?? []) : []) as $tab) {
            foreach ((array) ($tab['fields'] ?? []) as $field) {
                if (is_array($field) && ($field['type'] ?? null) === 'data-source') {
                    $names[] = (string) $field['name'];
                }
            }
        }
        sort($names, SORT_STRING);

        return $names;
    }

    /** @param  array<string, mixed>  $definition */
    private function label(array $definition, ?string $locale, ?string $fallback): string
    {
        $labels = $definition['editor']['labels'] ?? null;
        if (is_array($labels) && $labels !== []) {
            return (string) ($labels[(string) $locale] ?? $labels[(string) $fallback] ?? $labels[array_key_first($labels)]);
        }

        return (string) ($definition['label'] ?? '');
    }

    private function dataSourceLabel(string $id, ?string $locale): string
    {
        try {
            $definition = $this->dataSources->definition($id);

            return $definition === null ? $id : (string) RenderLocale::scope($locale, static fn (): string => $definition->label());
        } catch (Throwable) {
            return $id;
        }
    }
}
