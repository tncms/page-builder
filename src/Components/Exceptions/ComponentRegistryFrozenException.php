<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Components\Exceptions;

use RuntimeException;

/**
 * Thrown when a component provider is registered after the registry has frozen
 * for the request/application lifecycle (PB-XREG-1). The freeze point is a single
 * deterministic boundary ({@see \Illuminate\Contracts\Foundation\Application::booted()});
 * late registration is a programming error, not a silent no-op.
 */
final class ComponentRegistryFrozenException extends RuntimeException
{
    public static function forProvider(string $namespace): self
    {
        return new self(
            'Page Builder component registry is frozen; provider "'.$namespace.'" registered too late.'
        );
    }
}
