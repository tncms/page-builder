<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Components\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when a component identifier is malformed, out of bounds, or violates the
 * canonical `<provider-slug>:<local-id>` grammar (PB-XREG-1).
 */
final class InvalidComponentIdentifierException extends InvalidArgumentException
{
    public static function forValue(string $value): self
    {
        return new self('Invalid Page Builder component identifier: "'.$value.'".');
    }
}
