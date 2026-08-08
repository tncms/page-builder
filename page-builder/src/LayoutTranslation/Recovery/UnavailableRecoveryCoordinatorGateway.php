<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Recovery;

use Plugins\PageBuilder\LayoutTranslation\Recovery\Contracts\RecoveryCoordinatorGatewayInterface;

/**
 * The fail-closed default recovery execution seam (LT-9D).
 *
 * Layer: Translation (recovery / execution). Until the concrete adapter that lowers a confirmed recovery
 * into an {@see \Plugins\PageBuilder\LayoutTranslation\Execution\Commands\ExecuteSynchronizationCommand} and
 * hands it to the frozen Coordinator is wired (a later phase), recovery execution honestly refuses: it
 * returns a typed `failed` result and creates NOTHING. This preserves the seam and its delegation contract
 * (INV-LT-9D-03) while executing no synchronization — recovery never mutates history on its own
 * (INV-LT-9D-01). The analogue of the timeline's fail-closed Null snapshot backend.
 */
final class UnavailableRecoveryCoordinatorGateway implements RecoveryCoordinatorGatewayInterface
{
    public function execute(RecoveryExecutionRequest $request): RecoveryExecutionResult
    {
        return RecoveryExecutionResult::failed('recovery_coordinator_adapter_not_wired');
    }
}
