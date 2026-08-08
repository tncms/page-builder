<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Repository\Exceptions;

/**
 * Thrown when two resources claim the same RID with no deterministic winner —
 * i.e. contributed by providers of EQUAL priority (an ambiguous collision), or a
 * single provider yielding a duplicate RID.
 *
 * When providers have DIFFERENT priorities, the higher-priority provider wins
 * silently (documented conflict policy) and this exception is NOT raised.
 */
final class DuplicateRIDException extends RepositoryException
{
    public static function forRid(string $rid, string $providerA, string $providerB): self
    {
        return new self(
            "Duplicate RID '{$rid}' claimed by providers '{$providerA}' and '{$providerB}' at equal priority."
        );
    }

    public static function withinProvider(string $rid, string $provider): self
    {
        return new self("Provider '{$provider}' yielded a duplicate RID '{$rid}'.");
    }
}
