<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Management\Exceptions;

use RuntimeException;

/**
 * Thrown when a metadata edit fails validation (Phase 26.49, Rule 3).
 *
 * Only metadata fields are validated here — payload, fingerprint, RID and every
 * other system-managed field are never accepted for editing (Rules 9/10/19/20),
 * so they can never be invalid. The controller maps this to a 422 response and
 * returns the per-field issues.
 */
final class MetadataValidationException extends RuntimeException
{
    /**
     * @param  array<int, array{code: string, message: string}>  $issues
     */
    public function __construct(public readonly array $issues)
    {
        parent::__construct('The design metadata is invalid.');
    }
}
