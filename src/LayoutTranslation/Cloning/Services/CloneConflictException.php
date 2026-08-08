<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Cloning\Services;

use Plugins\PageBuilder\LayoutTranslation\Cloning\Results\LayoutTranslationCloneOutcome;
use RuntimeException;

/**
 * Internal signal to roll back the clone transaction with a typed outcome (LT-3).
 *
 * Layer: Translation (service internal). Thrown inside the transaction so all
 * writes roll back; the service catches it and maps `outcome` to the public typed
 * result. It never escapes the service boundary.
 */
final class CloneConflictException extends RuntimeException
{
    public function __construct(public readonly LayoutTranslationCloneOutcome $outcome)
    {
        parent::__construct($outcome->value);
    }
}
