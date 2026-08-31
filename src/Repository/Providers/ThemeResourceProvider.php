<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Repository\Providers;

use Plugins\PageBuilder\Contracts\DesignResourceProvider;
use Plugins\PageBuilder\Repository\DesignResource;
use Plugins\PageBuilder\Repository\Theme\ThemeResourceReader;

/**
 * Contributes the ACTIVE TN CMS theme's declarative Design Resources (patterns +
 * whole-page templates) to the one Design Resource Repository through the frozen
 * provider contract (PB-TEMPLATE-REGISTRY-1-H1).
 *
 * It owns no storage and no rendering: the {@see ThemeResourceReader} materialises
 * immutable resources from the active theme's `page-builder/{patterns,templates}`
 * data files. Every resource carries the `theme` source, so its RIDs are disjoint
 * from `builtin`/`local`/`imported`/`my-designs` and cannot collide with them; the
 * provider therefore never overrides another channel's resource by load order.
 *
 * When no theme is active (or it ships no Page Builder resources) the provider is
 * unavailable and contributes nothing — it is skipped, never an error. Revision is
 * content-derived by the reader, so the repository's revision-sum cache invalidates
 * automatically on a real content change (and stays stable across identical content
 * with different timestamps).
 */
final class ThemeResourceProvider implements DesignResourceProvider
{
    public const KEY = 'theme';

    /**
     * Lower precedence than the built-in channels (starter 90, local 100). Because
     * theme RIDs use a distinct `theme` source, priority never decides a cross-source
     * collision; this value simply orders theme resources after the built-ins.
     */
    private const PRIORITY = 110;

    public function __construct(
        private readonly ThemeResourceReader $reader,
    ) {
    }

    public function key(): string
    {
        return self::KEY;
    }

    public function priority(): int
    {
        return self::PRIORITY;
    }

    public function isAvailable(): bool
    {
        return $this->reader->hasActiveThemeResources();
    }

    public function revision(): int
    {
        return $this->reader->revision();
    }

    public function all(): iterable
    {
        yield from $this->reader->all();
    }

    public function find(string $rid): ?DesignResource
    {
        return $this->reader->find($rid);
    }
}
