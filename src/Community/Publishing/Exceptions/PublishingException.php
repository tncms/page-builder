<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Community\Publishing\Exceptions;

use Plugins\PageBuilder\Community\Publishing\ResourceStatus;
use RuntimeException;

/**
 * Thrown for illegal WORKFLOW operations — an unknown resource, or a lifecycle
 * transition the state machine forbids (Phase 26.50B).
 *
 * Validation failures are NOT exceptions: they are returned as a
 * {@see \Plugins\PageBuilder\Community\Publishing\PublishingResult} so a caller can
 * inspect the failed step and every field error.
 */
final class PublishingException extends RuntimeException
{
    public static function illegalTransition(ResourceStatus $from, ResourceStatus $to): self
    {
        return new self("Illegal lifecycle transition: {$from->value} → {$to->value}.");
    }

    public static function notFound(string $rid): self
    {
        return new self("Community resource '{$rid}' was not found.");
    }

    public static function manifestLocked(string $rid): self
    {
        return new self("Manifest '{$rid}' is published and immutable; edit via Working Copy → Capture → a new publish.");
    }
}
