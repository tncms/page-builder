<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Components\Exceptions;

use RuntimeException;

/**
 * Thrown when a component identifier is already owned by a different, previously
 * registered definition — within one provider's batch or across providers
 * (PB-XREG-1). Registration fails closed and never silently overwrites.
 */
final class DuplicateComponentIdentifierException extends RuntimeException
{
    public static function forIdentifier(string $identifier, string $owner): self
    {
        return new self(
            'Page Builder component identifier "'.$identifier.'" is already registered by "'.$owner.'".'
        );
    }
}
