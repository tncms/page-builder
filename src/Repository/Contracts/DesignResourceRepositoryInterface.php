<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Repository\Contracts;

use Plugins\PageBuilder\Repository\DesignResource;
use Plugins\PageBuilder\Repository\Exceptions\ResourceNotFoundException;
use Plugins\PageBuilder\Repository\ResourceCollection;
use Plugins\PageBuilder\Repository\ResourceQuery;

/**
 * The single source of truth for Design Resources.
 *
 * Consumers (preview, the future Design Library UI, import, distribution) MUST go
 * through this interface. They MUST NOT scan registries, package files, or
 * providers directly — the repository owns RID resolution, provider aggregation,
 * conflict policy, querying, and caching.
 *
 * FROZEN (Phase 26.49A). The repository is READ-oriented: it aggregates, resolves,
 * queries and searches — it never normalizes, validates, captures or mutates a
 * payload. The official, documented name is
 * {@see \Plugins\PageBuilder\Contracts\RepositoryContract}, which extends this
 * interface without adding methods. There is exactly ONE repository.
 */
interface DesignResourceRepositoryInterface
{
    /**
     * Register a provider. Idempotent by provider key (re-registering the same key
     * replaces the previous instance) and invalidates the cache.
     */
    public function registerProvider(DesignResourceProviderInterface $provider): void;

    public function has(string $rid): bool;

    /**
     * @throws ResourceNotFoundException
     */
    public function find(string $rid): DesignResource;

    public function tryFind(string $rid): ?DesignResource;

    public function all(): ResourceCollection;

    public function query(ResourceQuery $query): ResourceCollection;

    /**
     * Drop the in-memory cache. Rarely needed — the repository invalidates
     * automatically when providers change (via their revision tokens).
     */
    public function refresh(): void;
}
