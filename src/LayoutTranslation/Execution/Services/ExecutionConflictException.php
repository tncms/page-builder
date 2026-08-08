<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Execution\Services;

use Plugins\PageBuilder\LayoutTranslation\Execution\Results\SynchronizationExecutionOutcome;
use RuntimeException;

/**
 * An internal, in-transaction abort carrying the typed outcome to return (LT-7B3).
 *
 * Layer: Translation (coordinator). Mirrors the LT-3 `CloneConflictException` pattern: thrown inside the
 * execution transaction to roll everything back and surface a typed
 * {@see SynchronizationExecutionOutcome} at the service boundary. Because it is thrown before or in place
 * of any write, the transaction leaves the target, baseline, and history unchanged.
 */
final class ExecutionConflictException extends RuntimeException
{
    public function __construct(
        public readonly SynchronizationExecutionOutcome $outcome,
        public readonly ?string $detail = null,
    ) {
        parent::__construct("Synchronization aborted: {$outcome->value}");
    }
}
