<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Management\Exceptions;

use RuntimeException;

/**
 * Thrown when a "My Designs" record cannot be found for a given RID — because it
 * never existed or has been soft-deleted (Phase 26.49, Rule 8).
 *
 * Deleted resources are never permanently removed, but they are invisible to
 * management operations: attempting to act on one raises this. The controller
 * maps it to a 404 response.
 */
final class DesignResourceNotFoundException extends RuntimeException
{
    public static function forRid(string $rid): self
    {
        return new self('No manageable design found for '.$rid.'.');
    }
}
