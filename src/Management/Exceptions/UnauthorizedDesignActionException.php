<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Management\Exceptions;

use RuntimeException;

/**
 * Thrown when the current actor is not the owner (nor an authorized admin) of a
 * "My Designs" resource they are trying to manage (Phase 26.49, Rule 16).
 *
 * Ownership is always verified server-side against the record's stored author;
 * the client's claimed ownership is never trusted. The controller maps this to a
 * 403 response.
 */
final class UnauthorizedDesignActionException extends RuntimeException
{
    public static function forRid(string $rid): self
    {
        return new self('You are not allowed to manage the design '.$rid.'.');
    }
}
