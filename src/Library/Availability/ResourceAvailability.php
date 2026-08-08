<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Library\Availability;

/**
 * Immutable availability verdict for one Design Resource, for the current actor.
 *
 * Two orthogonal dimensions:
 *   - {@see $available}: does the resource resolve at all? (a stale RID is not)
 *   - {@see $locked}:    does it require a capability the actor lacks?
 *
 * A locked resource is still {@see $available} — it can be listed and PREVIEWED;
 * only import/insert is gated. Licensing therefore never affects identity or
 * rendering, only this verdict (Rule 2 of the FREE/PRO boundary).
 */
final class ResourceAvailability
{
    public function __construct(
        public readonly bool $available,
        public readonly bool $locked,
        public readonly ?string $requiredCapability = null,
    ) {
    }

    /** Available and unlocked — a FREE resource, or a PRO resource the actor owns. */
    public static function open(): self
    {
        return new self(true, false, null);
    }

    /** Available but locked behind a capability the actor does not hold. */
    public static function locked(string $capability): self
    {
        return new self(true, true, $capability);
    }

    /** The resource does not resolve (e.g. a stale favorite). */
    public static function unavailable(): self
    {
        return new self(false, false, null);
    }

    /** Whether the actor may import/insert this resource right now. */
    public function isImportable(): bool
    {
        return $this->available && ! $this->locked;
    }
}
