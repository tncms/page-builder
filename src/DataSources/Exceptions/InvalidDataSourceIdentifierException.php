<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\DataSources\Exceptions;

use InvalidArgumentException;

/** Thrown when a string is not a well-formed `provider:local` data-source identifier (PB-XREG-2). */
final class InvalidDataSourceIdentifierException extends InvalidArgumentException
{
    public static function forValue(string $value): self
    {
        return new self('Invalid Page Builder data-source identifier "'.mb_substr($value, 0, 140).'".');
    }
}
