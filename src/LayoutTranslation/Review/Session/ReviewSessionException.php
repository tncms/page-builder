<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Review\Session;

use RuntimeException;

/**
 * An illegal review-session lifecycle transition (LT-8B, per LT-8A-REVIEW-WORKSPACE-ARCHITECTURE §2.2).
 *
 * Layer: Translation (review session). Thrown when a session is driven out of order (e.g. submit after a
 * terminal state, or pause while not in review). Fail-closed: the runtime refuses the transition rather
 * than performing an unsafe action.
 */
final class ReviewSessionException extends RuntimeException
{
    public static function cannot(string $action, ReviewSessionState $state): self
    {
        return new self("Cannot {$action} a review session in state '{$state->value}'.");
    }
}
