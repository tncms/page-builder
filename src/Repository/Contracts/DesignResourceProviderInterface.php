<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Repository\Contracts;

use Plugins\PageBuilder\Repository\DesignResource;

/**
 * A source of Design Resources (builtin components, imported packages, and — in
 * the future — remote/cloud channels). Providers register themselves with the
 * repository; the repository aggregates and de-conflicts them.
 *
 * Providers are storage-agnostic to the repository: how a provider materialises
 * its resources (registries, package files, network) is entirely its own concern.
 *
 * FROZEN (Phase 26.49A). This is the established provider surface; the official,
 * documented name is {@see \Plugins\PageBuilder\Contracts\DesignResourceProvider},
 * which extends this interface without adding methods. Do not add or change methods
 * here — the contract is stable and future providers must comply with it exactly.
 */
interface DesignResourceProviderInterface
{
    /**
     * Stable, unique provider key (e.g. 'local', 'package'). Used for
     * idempotent registration and conflict reporting.
     */
    public function key(): string;

    /**
     * Resolution precedence. LOWER numbers are resolved FIRST (higher precedence).
     * When two providers of DIFFERENT priority claim the same RID, the lower
     * number wins deterministically. EQUAL priority + same RID is a conflict.
     */
    public function priority(): int;

    /**
     * Whether this provider can currently serve resources. An unavailable
     * provider contributes nothing (it is skipped, not an error).
     */
    public function isAvailable(): bool;

    /**
     * A cheap monotonic token that changes whenever this provider's resource set
     * changes. The repository uses the sum of provider revisions to invalidate its
     * cache automatically — no manual invalidation required.
     */
    public function revision(): int;

    /**
     * All resources this provider currently offers.
     *
     * @return iterable<DesignResource>
     */
    public function all(): iterable;

    /**
     * Resolve a single resource by RID, or null if this provider does not have it.
     */
    public function find(string $rid): ?DesignResource;
}
