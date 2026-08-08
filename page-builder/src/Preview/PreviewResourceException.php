<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Preview;

use RuntimeException;

/**
 * Thrown when a preview cannot be resolved for the requested source/reference
 * (e.g. an unknown source key or an unregistered component type). Callers should
 * translate this into a 404.
 */
final class PreviewResourceException extends RuntimeException
{
    public static function unknownSource(string $source): self
    {
        return new self("Unknown preview source: {$source}");
    }

    public static function unknownType(string $kind, string $type): self
    {
        return new self("Unknown {$kind} type for preview: {$type}");
    }

    public static function missingReference(string $source): self
    {
        return new self("Preview source '{$source}' requires a reference.");
    }
}
