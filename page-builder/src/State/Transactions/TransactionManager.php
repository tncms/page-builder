<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\State\Transactions;

use Plugins\PageBuilder\State\BuilderState;

/**
 * Executes a {@see Transaction} as a single undoable step (Phase 8).
 *
 * Commands run directly against the state (not through the per-command
 * {@see \Plugins\PageBuilder\State\CommandBus} snapshotting) so the whole group
 * produces exactly one history snapshot — of the payload as it was before the
 * transaction — and marks the state dirty once, only if the group changed it.
 */
final class TransactionManager
{
    public function run(BuilderState $state, Transaction $transaction): BuilderState
    {
        $before = $state->payload();

        foreach ($transaction->commands() as $command) {
            $state = $command->execute($state);
        }

        if ($before !== $state->payload()) {
            $state->history()->snapshot($before, $transaction->label());
            $state->markDirty();
        }

        return $state;
    }
}
