<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Package\Exceptions;

use RuntimeException;

/**
 * Thrown when a resource cannot be exported into a Design Package (it does not
 * resolve, or its payload violates the portable-package boundary — e.g. an unsafe
 * asset URL or a host-storage reference).
 */
final class PackageExportException extends RuntimeException
{
    public function __construct(
        public readonly string $reasonCode,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function resourceNotFound(string $rid): self
    {
        return new self('resource_not_found', "Resource not found: {$rid}.");
    }

    public static function unsupportedKind(string $kind): self
    {
        return new self('unsupported_kind', "Kind '{$kind}' cannot be exported (kits are not supported yet).");
    }

    /**
     * @param  array<int, string>  $values
     */
    public static function unsafeAsset(array $values): self
    {
        return new self('unsafe_asset', 'The resource references an unsafe asset URL: '.implode(', ', $values));
    }

    /**
     * @param  array<int, string>  $keys
     */
    public static function forbiddenReference(array $keys): self
    {
        return new self('forbidden_reference', 'The resource references host storage identities: '.implode(', ', $keys));
    }
}
