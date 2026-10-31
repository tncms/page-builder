<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Exceptions;

use RuntimeException;

/**
 * An editor draft carries author HTML over the authoring bounds (PB-TEXT-AUTHORING-R1).
 * Carries only a bounded reason (`too_large` / `too_deep`) plus the node id and field.
 */
final class AuthoredHtmlRejectedException extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        public readonly string $nodeId,
        public readonly string $field,
    ) {
        parent::__construct('Authored HTML refused ('.$reason.') on field "'.$field.'".');
    }
}
