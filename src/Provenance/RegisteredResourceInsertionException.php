<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Provenance;

use RuntimeException;

/**
 * A bounded, namespaced failure of the registered-resource insertion orchestration
 * (PB-TREG-2). The whole operation is rejected atomically with a stable machine code —
 * never a signature, key, token body, path, or internal detail.
 */
final class RegisteredResourceInsertionException extends RuntimeException
{
    public const NOT_FOUND = 'not_found';
    public const UNSUPPORTED_KIND = 'unsupported_kind';
    public const UNAVAILABLE = 'unavailable';
    public const EMPTY_RESOURCE = 'empty_resource';
    public const TARGET_REQUIRED = 'target_required';
    public const ROOT_EXTRACTION = 'root_extraction';
    public const ATTRIBUTION = 'attribution';

    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }
}
