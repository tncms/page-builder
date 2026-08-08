<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Recovery\Contracts;

use Plugins\PageBuilder\LayoutTranslation\Recovery\RecoveryExecutionRequest;
use Plugins\PageBuilder\LayoutTranslation\Recovery\RecoveryExecutionResult;

/**
 * The recovery execution seam over the frozen Coordinator (LT-9D, per INV-LT-9D-03).
 *
 * Layer: Translation (recovery / execution port). Recovery NEVER performs synchronization itself — it
 * delegates execution across this seam to the existing frozen {@see
 * \Plugins\PageBuilder\LayoutTranslation\Execution\Contracts\SynchronizationCoordinatorInterface}, which
 * remains the sole execution authority. The adapter creates a NEW execution (new append-only history + new
 * baseline + new timeline entry) and maps the coordinator's typed outcome to a {@see RecoveryExecutionResult}.
 * It never edits history, snapshots or baselines, and never replaces an execution (INV-LT-9D-01/04). The
 * concrete command-building adapter is a later wiring step.
 */
interface RecoveryCoordinatorGatewayInterface
{
    public function execute(RecoveryExecutionRequest $request): RecoveryExecutionResult;
}
