<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Repository\Providers;

use Plugins\PageBuilder\Capture\UserDesignStore;
use Plugins\PageBuilder\Contracts\DesignResourceProvider;
use Plugins\PageBuilder\Repository\DesignResource;
use Plugins\PageBuilder\Repository\Rid;

/**
 * Serves captured "My Designs" resources from the UserDesignStore (Phase 26.48).
 *
 * This makes a captured design queryable in the repository the moment it is
 * persisted (so Save as Design's persist + refresh chain is real and testable).
 * The dedicated My Designs *palette* group is NOT surfaced yet (Phase 26.47C/E
 * reserved it): this repository provider is registered, but no My Designs provider
 * is registered with the BuilderResourceRegistry, so the Builder palette does not
 * show these — the source stays "internal" per Rule 18. The `source` is always
 * forced to `my-designs`; a stored value is never trusted.
 */
final class UserDesignProvider implements DesignResourceProvider
{
    public const KEY = 'user-designs';

    public const SOURCE = 'my-designs';

    /** @var array<string, DesignResource>|null */
    private ?array $cache = null;

    private ?string $cacheSignature = null;

    public function __construct(
        private readonly UserDesignStore $store,
    ) {
    }

    public function key(): string
    {
        return self::KEY;
    }

    public function priority(): int
    {
        return 30;
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function revision(): int
    {
        return (int) (crc32($this->store->signature()) % 2147483647);
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
        if ($this->cache !== null && $this->cacheSignature === $signature) {
            return $this->cache;
        }

        $resources = [];
        foreach ($this->store->all() as $record) {
            $resource = $this->materialize($record);
            if ($resource !== null) {
                $resources[$resource->rid] = $resource;
            }
        }

        $this->cache = $resources;
        $this->cacheSignature = $signature;

        return $resources;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function materialize(array $record): ?DesignResource
    {
        // Archived and soft-deleted designs are hidden from the repository, and so
        // from normal browsing and the Builder palette (Phase 26.49, Rules 6/8).
        // The management surface reads the store directly to still surface archived
        // ones under its Archived filter.
        if (($record['archived'] ?? false) === true || ($record['deleted'] ?? false) === true) {
            return null;
        }

        $kind = $record['kind'] ?? '';
        $resourceId = $record['resourceId'] ?? '';

        if (! in_array($kind, [Rid::KIND_SECTION, Rid::KIND_BLOCK], true) || ! is_string($resourceId) || $resourceId === '') {
            return null;
        }

        $rid = self::SOURCE.':'.$kind.':'.$resourceId;
        if (! Rid::isValid($rid)) {
            return null;
        }

        return new DesignResource(
            rid: $rid,
            source: self::SOURCE,
            kind: (string) $kind,
            resourceId: $resourceId,
            version: is_string($record['version'] ?? null) ? $record['version'] : '1.0.0',
            name: is_string($record['name'] ?? null) ? $record['name'] : $resourceId,
            category: is_string($record['category'] ?? null) ? $record['category'] : 'general',
            tags: is_array($record['tags'] ?? null) ? array_values(array_filter($record['tags'], 'is_string')) : [],
            payload: is_array($record['payload'] ?? null) ? $record['payload'] : [],
            vendor: self::SOURCE,
            description: is_string($record['description'] ?? null) ? $record['description'] : null,
            preview: is_array($record['preview'] ?? null) ? $record['preview'] : null,
            compatibility: is_array($record['compatibility'] ?? null) ? $record['compatibility'] : null,
            distribution: is_array($record['distribution'] ?? null) ? $record['distribution'] : [],
        );
    }
}
