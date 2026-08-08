<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Community\Manifest\Exceptions;

use RuntimeException;

/**
 * Thrown when a manifest cannot be PARSED at all — malformed JSON, or a top-level
 * shape that is not an object (Phase 26.50A, Rule 15).
 *
 * Semantic validity (bad rid, unknown license, missing compatibility, …) is NOT an
 * exception: it is reported field-by-field by the {@see \Plugins\PageBuilder\Community\Manifest\ManifestValidator}.
 */
final class InvalidManifestException extends RuntimeException
{
    public static function notJson(string $detail): self
    {
        return new self('Manifest is not valid JSON: '.$detail);
    }

    public static function notAnObject(): self
    {
        return new self('Manifest must be a JSON object.');
    }
}
