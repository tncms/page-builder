<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Repository\Providers;

use Plugins\PageBuilder\Contracts\DesignResourceProvider;
use Plugins\PageBuilder\Repository\DesignResource;
use Plugins\PageBuilder\Repository\Starters\StarterResourceCatalog;

/**
 * Serves the FREE starter Design Resources (Phase 26.46) — curated pages and
 * sections — through the Design Resource Repository.
 *
 * It owns no storage of its own: the {@see StarterResourceCatalog} materialises the
 * resources in memory. Because every starter has a distinct resourceId, its RIDs
 * never collide with the raw component resources from {@see LocalResourceProvider};
 * both providers share the `builtin` source without conflict.
 */
final class StarterResourceProvider implements DesignResourceProvider
{
    public const KEY = 'starter';

    // Bump when the starter catalog content changes so the repository cache
    // invalidates deterministically (see revision()).
    private const REVISION = 26_46_0001;

    public function __construct(
        private readonly StarterResourceCatalog $catalog,
    ) {
    }

    public function key(): string
    {
        return self::KEY;
    }

    public function priority(): int
    {
        // Higher precedence than the raw-component fallback (100); irrelevant in
        // practice since starter RIDs are disjoint from LocalResourceProvider's.
        return 90;
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function revision(): int
    {
        return self::REVISION;
    }

    public function all(): iterable
    {
        yield from $this->catalog->all();
    }

    public function find(string $rid): ?DesignResource
    {
        return $this->catalog->find($rid);
    }
}
