<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\DataSources\Exceptions;

use RuntimeException;

/** Thrown when a data-source identifier is already owned by a different registration (PB-XREG-2). */
final class DuplicateDataSourceIdentifierException extends RuntimeException
{
    public static function forIdentifier(string $id, string $owner): self
    {
        return new self('Page Builder data source "'.$id.'" is already registered by "'.$owner.'".');
    }
}
