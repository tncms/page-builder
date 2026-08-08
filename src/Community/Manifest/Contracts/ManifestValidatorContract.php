<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Community\Manifest\Contracts;

use Plugins\PageBuilder\Community\Manifest\ManifestValidationResult;

/**
 * The manifest validation contract (Phase 26.50A, Rule 15).
 *
 * The Community validator implements this; a future Marketplace / Enterprise
 * validator EXTENDS it (adding license/purchase checks) without replacing the
 * shared manifest validation the whole platform relies on (Rule 12).
 *
 * @see docs/manifest-validator.md
 */
interface ManifestValidatorContract
{
    /**
     * Validate a manifest (a {@see ManifestContract} or its array form) and return a
     * structured result. Malformed manifests are rejected, never thrown past here.
     *
     * @param  ManifestContract|array<string, mixed>  $manifest
     */
    public function validate(ManifestContract|array $manifest): ManifestValidationResult;
}
