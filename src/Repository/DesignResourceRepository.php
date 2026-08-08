<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Repository;

use Plugins\PageBuilder\Contracts\RepositoryContract;
use Plugins\PageBuilder\Repository\Contracts\DesignResourceProviderInterface;
use Plugins\PageBuilder\Repository\Exceptions\DuplicateRIDException;
use Plugins\PageBuilder\Repository\Exceptions\ResourceNotFoundException;

/**
 * Aggregates every registered provider into one deterministic, cached index of
 * Design Resources keyed by RID.
 *
 * Conflict policy (documented, deterministic):
 *   - Providers are ordered by (priority ASC, key ASC). Lower priority wins.
 *   - The first provider (in that order) to claim a RID owns it.
 *   - A later provider of DIFFERENT priority claiming the same RID is skipped.
 *   - A provider of EQUAL priority claiming an already-owned RID is an ambiguous
 *     collision → DuplicateRIDException.
 *
 * Caching: the index is built lazily and cached. It is rebuilt automatically when
 * any provider's revision() changes (the repository compares the sum of provider
 * revisions). The cache is in-memory only — no persistent storage. Cached
 * DesignResource objects are immutable, so sharing them is safe.
 */
final class DesignResourceRepository implements RepositoryContract
{
    /** @var array<string, DesignResourceProviderInterface> keyed by provider key */
    private array $providers = [];

    /** @var array<string, DesignResource>|null rid => resource */
    private ?array $index = null;

    private int $builtAtSignature = -1;

    public function registerProvider(DesignResourceProviderInterface $provider): void
    {
        $this->providers[$provider->key()] = $provider;
        $this->index = null;
    }

    public function has(string $rid): bool
    {
        return isset($this->index()[$rid]);
    }

    public function find(string $rid): DesignResource
    {
        return $this->index()[$rid] ?? throw ResourceNotFoundException::forRid($rid);
    }

    public function tryFind(string $rid): ?DesignResource
    {
        return $this->index()[$rid] ?? null;
    }

    public function all(): ResourceCollection
    {
        return $this->sorted(array_values($this->index()), ResourceQuery::make());
    }

    public function query(ResourceQuery $query): ResourceCollection
    {
        $matched = array_values(array_filter(
            $this->index(),
            static fn (DesignResource $r): bool => $query->matches($r),
        ));

        return $this->sorted($matched, $query);
    }

    public function refresh(): void
    {
        $this->index = null;
    }

    /**
     * @return array<string, DesignResource>
     */
    private function index(): array
    {
        $signature = $this->providerSignature();

        if ($this->index !== null && $signature === $this->builtAtSignature) {
            return $this->index;
        }

        $this->index = $this->build();
        $this->builtAtSignature = $signature;

        return $this->index;
    }

    /**
     * @return array<string, DesignResource>
     */
    private function build(): array
    {
        $index = [];
        /** @var array<string, DesignResourceProviderInterface> $owner */
        $owner = [];

        foreach ($this->orderedProviders() as $provider) {
            if (! $provider->isAvailable()) {
                continue;
            }

            foreach ($provider->all() as $resource) {
                $rid = $resource->rid;

                if (! isset($index[$rid])) {
                    $index[$rid] = $resource;
                    $owner[$rid] = $provider;

                    continue;
                }

                // Already owned. Equal priority => ambiguous collision.
                if ($owner[$rid]->priority() === $provider->priority()) {
                    throw DuplicateRIDException::forRid($rid, $owner[$rid]->key(), $provider->key());
                }

                // Different priority: the earlier (higher-precedence) owner wins.
            }
        }

        return $index;
    }

    /**
     * @return array<int, DesignResourceProviderInterface>
     */
    private function orderedProviders(): array
    {
        $providers = array_values($this->providers);

        usort($providers, static function (DesignResourceProviderInterface $a, DesignResourceProviderInterface $b): int {
            return [$a->priority(), $a->key()] <=> [$b->priority(), $b->key()];
        });

        return $providers;
    }

    private function providerSignature(): int
    {
        $sum = 0;
        foreach ($this->providers as $provider) {
            // Fold the key so that adding/removing providers also changes the signature.
            $sum += $provider->revision() + (int) (crc32($provider->key()) % 100000);
        }

        return $sum + count($this->providers);
    }

    /**
     * @param  array<int, DesignResource>  $resources
     */
    private function sorted(array $resources, ResourceQuery $query): ResourceCollection
    {
        usort($resources, function (DesignResource $a, DesignResource $b) use ($query): int {
            $cmp = match ($query->orderBy) {
                ResourceQuery::ORDER_NAME => [$a->name, $a->rid] <=> [$b->name, $b->rid],
                ResourceQuery::ORDER_VERSION => [$this->versionKey($a->version), $a->rid] <=> [$this->versionKey($b->version), $b->rid],
                default => $a->rid <=> $b->rid,
            };

            return $query->descending ? -$cmp : $cmp;
        });

        return new ResourceCollection($resources);
    }

    /**
     * @return array<int, int>
     */
    private function versionKey(string $version): array
    {
        $parts = array_map('intval', explode('.', $version));

        return [$parts[0] ?? 0, $parts[1] ?? 0, $parts[2] ?? 0];
    }
}
