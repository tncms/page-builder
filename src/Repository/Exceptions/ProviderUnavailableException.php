<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Repository\Exceptions;

/**
 * Thrown when a provider that is required for an operation reports itself
 * unavailable (e.g. a remote/cloud provider that cannot be reached).
 */
final class ProviderUnavailableException extends RepositoryException
{
    public static function forProvider(string $providerKey): self
    {
        return new self("Design resource provider is unavailable: {$providerKey}");
    }
}
