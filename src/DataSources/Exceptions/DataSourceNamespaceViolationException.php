<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\DataSources\Exceptions;

use RuntimeException;

/**
 * Thrown when a data-source provider's declared namespace cannot be confirmed as
 * the canonical installed-plugin slug that owns it (PB-XREG-2).
 */
final class DataSourceNamespaceViolationException extends RuntimeException
{
    public static function forReason(string $reason): self
    {
        return new self('Page Builder data-source provider namespace violation: '.$reason);
    }
}
