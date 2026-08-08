<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Library\Availability;

use Plugins\PageBuilder\Repository\DesignResource;

/**
 * Resolves whether a Design Resource is available (and unlocked) for the current
 * actor, using only its distribution metadata (C8) and the {@see CapabilityGate}.
 *
 * The FREE/PRO boundary lives entirely here:
 *   - a resource with NO required capability is always open (this is every FREE
 *     builtin/user/imported resource today — so behaviour is unchanged);
 *   - a resource that declares a required capability is locked unless the gate
 *     grants it.
 *
 * It never mutates the resource, never touches billing, and never reads anything
 * outside the repository-provided metadata. Identity (source:kind:resourceId) is
 * untouched — licensing is metadata, per Rule 2 of the FREE/PRO boundary.
 */
final class ResourceAvailabilityResolver
{
    public function __construct(
        private readonly CapabilityGate $gate,
    ) {
    }

    public function resolve(DesignResource $resource): ResourceAvailability
    {
        $capability = $this->requiredCapabilityFor($resource);

        if ($capability === null) {
            return ResourceAvailability::open();
        }

        return $this->gate->allows($capability)
            ? ResourceAvailability::open()
            : ResourceAvailability::locked($capability);
    }

    public function isImportable(DesignResource $resource): bool
    {
        return $this->resolve($resource)->isImportable();
    }

    /**
     * The capability required to import/insert this resource, or null when it is
     * freely available. Read from distribution metadata (C8) in two accepted forms:
     *
     *   distribution.requiredCapability = "page-builder.pro"           (flat)
     *   distribution.entitlement.required_capability = "page-builder.pro" (nested)
     *
     * The legacy flat `entitlement` string (e.g. "free") is a display label only and
     * never gates access — enforcement is always capability-driven.
     */
    public function requiredCapabilityFor(DesignResource $resource): ?string
    {
        $distribution = $resource->distribution;

        $flat = $distribution['requiredCapability'] ?? null;
        if (is_string($flat) && $flat !== '') {
            return $flat;
        }

        $entitlement = $distribution['entitlement'] ?? null;
        if (is_array($entitlement)) {
            $nested = $entitlement['required_capability'] ?? $entitlement['requiredCapability'] ?? null;

            return is_string($nested) && $nested !== '' ? $nested : null;
        }

        return null;
    }
}
