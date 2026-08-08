<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Repository\Providers;

use Plugins\PageBuilder\Contracts\DesignResourceProvider;
use Plugins\PageBuilder\Package\ImportedPackageStore;
use Plugins\PageBuilder\Repository\DesignResource;
use Plugins\PageBuilder\Repository\Rid;

/**
 * Serves resources contributed by locally imported Design Packages (Phase 26.47).
 *
 * It reads the persistent {@see ImportedPackageStore} and materialises each package
 * resource as an ordinary {@see DesignResource} with `source = imported`. Because
 * the source is fixed here, imported RIDs (`imported:<kind>:<id>`) can NEVER collide
 * with `builtin` (starter/local) or `user` resources — imports never overwrite them.
 *
 * Consumers still go through the repository; this provider only registers with it.
 */
final class ImportedResourceProvider implements DesignResourceProvider
{
    public const KEY = 'imported';
    public const SOURCE = 'imported';

    /** @var array<string, DesignResource>|null in-request cache */
    private ?array $resources = null;

    private ?string $signature = null;

    public function __construct(
        private readonly ImportedPackageStore $store,
    ) {
    }

    public function key(): string
    {
        return self::KEY;
    }

    public function priority(): int
    {
        // Higher precedence than builtin (100) and the package seam (50). Moot in
        // practice: the `imported` source keeps these RIDs disjoint from all others.
        return 40;
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function revision(): int
    {
        // Changes whenever the store changes, so the repository cache invalidates.
        $signature = $this->store->signature();

        return $signature === '' ? 0 : (int) hexdec($signature);
    }

    public function all(): iterable
    {
        return array_values($this->load());
    }

    public function find(string $rid): ?DesignResource
    {
        return $this->load()[$rid] ?? null;
    }

    /**
     * @return array<string, DesignResource>
     */
    private function load(): array
    {
        $signature = $this->store->signature();
        if ($this->resources !== null && $this->signature === $signature) {
            return $this->resources;
        }

        $resources = [];
        foreach ($this->store->all() as $package) {
            foreach (($package['resources'] ?? []) as $entry) {
                if (! is_array($entry)) {
                    continue;
                }
                $resource = $this->materialize($entry);
                if ($resource !== null) {
                    $resources[$resource->rid] = $resource;
                }
            }
        }

        $this->resources = $resources;
        $this->signature = $signature;

        return $resources;
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function materialize(array $entry): ?DesignResource
    {
        $kind = is_string($entry['kind'] ?? null) ? $entry['kind'] : '';
        $resourceId = is_string($entry['resourceId'] ?? null) ? $entry['resourceId'] : '';

        if (! in_array($kind, [Rid::KIND_BLOCK, Rid::KIND_SECTION, Rid::KIND_PAGE], true)) {
            return null;
        }
        // Source is FIXED to `imported` — the package's own source is never trusted.
        $rid = self::SOURCE.':'.$kind.':'.$resourceId;
        if (! Rid::isValid($rid)) {
            return null;
        }

        return new DesignResource(
            rid: $rid,
            source: self::SOURCE,
            kind: $kind,
            resourceId: $resourceId,
            version: is_string($entry['version'] ?? null) ? $entry['version'] : '1.0.0',
            name: is_string($entry['name'] ?? null) ? $entry['name'] : $resourceId,
            category: is_string($entry['category'] ?? null) ? $entry['category'] : 'imported',
            tags: is_array($entry['tags'] ?? null) ? array_values(array_filter($entry['tags'], 'is_string')) : [],
            payload: is_array($entry['payload'] ?? null) ? $entry['payload'] : [],
            members: is_array($entry['members'] ?? null) ? array_values(array_filter($entry['members'], 'is_string')) : [],
            vendor: is_string($entry['vendor'] ?? null) ? $entry['vendor'] : self::SOURCE,
            description: is_string($entry['description'] ?? null) ? $entry['description'] : null,
            preview: is_array($entry['preview'] ?? null) ? $entry['preview'] : null,
            compatibility: is_array($entry['compatibility'] ?? null) ? $entry['compatibility'] : null,
            distribution: is_array($entry['distribution'] ?? null) ? $entry['distribution'] : [],
        );
    }
}
