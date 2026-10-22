<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\DataSources\Exceptions;

use RuntimeException;

/**
 * Thrown when a data-source provider registers after the registry froze at
 * {@see \Illuminate\Contracts\Foundation\Application::booted()} (PB-XREG-2).
 * Late registration is a programming error, not a silent no-op.
 */
final class DataSourceRegistryFrozenException extends RuntimeException
{
    public static function forProvider(string $namespace): self
    {
        return new self(
            'Page Builder data-source registry is frozen; provider "'.$namespace.'" registered too late.'
        );
    }
}
