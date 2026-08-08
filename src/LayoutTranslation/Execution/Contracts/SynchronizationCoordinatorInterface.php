<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Execution\Contracts;

use Plugins\PageBuilder\LayoutTranslation\Execution\Commands\ExecuteSynchronizationCommand;
use Plugins\PageBuilder\LayoutTranslation\Execution\Results\SynchronizationExecutionResult;

/**
 * The synchronization coordinator boundary (LT-7B3).
 *
 * Layer: Translation (execution orchestration). The coordinator owns loading, provenance verification,
 * eligibility, mutation, persistence, history, the transaction, and typed results. It always returns a
 * typed {@see SynchronizationExecutionResult} — never a raw exception, SQL, model or row.
 */
interface SynchronizationCoordinatorInterface
{
    public function execute(ExecuteSynchronizationCommand $command): SynchronizationExecutionResult;
}
