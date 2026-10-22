<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Components\Exceptions;

use RuntimeException;

/**
 * Thrown when a provider's declared namespace cannot be confirmed as its canonical
 * installed plugin slug, claims another plugin's namespace, uses the reserved
 * Page Builder namespace, or registers a component whose identifier namespace does
 * not match the provider (PB-XREG-1). Identity is derived server-side; a
 * self-asserted namespace is never trusted.
 */
final class ComponentNamespaceViolationException extends RuntimeException
{
    public static function forReason(string $reason): self
    {
        return new self('Page Builder component namespace violation: '.$reason);
    }
}
