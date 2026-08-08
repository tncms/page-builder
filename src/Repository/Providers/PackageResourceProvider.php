<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Repository\Providers;

use Plugins\PageBuilder\Contracts\DesignResourceProvider;
use Plugins\PageBuilder\Repository\DesignResource;
use Plugins\PageBuilder\Repository\Exceptions\DuplicateRIDException;

/**
 * Holds Design Resources contributed by imported packages.
 *
 * This is the registration seam for the (future) package importer: once a package
 * passes validation it registers each of its resources here, and they immediately
 * become discoverable through the repository — importers never write to the
 * repository's index directly, they only register with a provider.
 *
 * Resources are held in-memory (no persistent storage). Registration is idempotent
 * for byte-identical resources and rejects genuine RID collisions.
 */
final class PackageResourceProvider implements DesignResourceProvider
{
    public const KEY = 'package';

    /** @var array<string, DesignResource> rid => resource */
    private array $resources = [];

    private int $revision = 0;

    public function key(): string
    {
        return self::KEY;
    }

    public function priority(): int
    {
        // Imported packages take precedence over builtin fallbacks.
        return 50;
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function revision(): int
    {
        return $this->revision;
    }

    public function all(): iterable
    {
        return array_values($this->resources);
    }

    public function find(string $rid): ?DesignResource
    {
        return $this->resources[$rid] ?? null;
    }

    /**
     * Register a resource contributed by an imported package.
     *
     * Idempotent when an identical resource (same RID + same payload) is already
     * present; throws on a genuine collision (same RID, different content).
     *
     * @throws DuplicateRIDException
     */
    public function register(DesignResource $resource): void
    {
        $existing = $this->resources[$resource->rid] ?? null;

        if ($existing !== null) {
            if ($this->identical($existing, $resource)) {
                return; // idempotent
            }

            throw DuplicateRIDException::withinProvider($resource->rid, self::KEY);
        }

        $this->resources[$resource->rid] = $resource;
        $this->revision++;
    }

    public function forget(string $rid): void
    {
        if (isset($this->resources[$rid])) {
            unset($this->resources[$rid]);
            $this->revision++;
        }
    }

    public function flush(): void
    {
        if ($this->resources !== []) {
            $this->resources = [];
            $this->revision++;
        }
    }

    private function identical(DesignResource $a, DesignResource $b): bool
    {
        return $a->version === $b->version
            && $a->payload === $b->payload
            && $a->members === $b->members;
    }
}
