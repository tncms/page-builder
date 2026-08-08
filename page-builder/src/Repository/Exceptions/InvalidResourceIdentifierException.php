<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Repository\Exceptions;

/**
 * Thrown when a string is not a well-formed RID (`<source>:<kind>:<resourceId>`).
 */
final class InvalidResourceIdentifierException extends RepositoryException
{
    public static function forValue(string $value): self
    {
        return new self("Invalid design resource identifier (RID): '{$value}'");
    }
}
