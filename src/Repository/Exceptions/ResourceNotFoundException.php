<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Repository\Exceptions;

/**
 * Thrown when no resource matches a requested RID.
 */
final class ResourceNotFoundException extends RepositoryException
{
    public static function forRid(string $rid): self
    {
        return new self("Design resource not found: {$rid}");
    }
}
