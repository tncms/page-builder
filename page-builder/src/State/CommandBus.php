<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\State;

use Plugins\PageBuilder\State\Commands\Command;
use Plugins\PageBuilder\State\Commands\ManagesOwnHistory;

/**
 * Runs commands against a {@see BuilderState}, taking care of undo history and
 * the dirty flag so individual commands don't have to (Phase 8).
 *
 * A command's payload effect is detected by comparing the payload before and
 * after execution: only a real change snapshots the previous payload for undo
 * and marks the state dirty. Selection-only commands (select/hover/copy) leave
 * the payload untouched and therefore neither snapshot nor dirty. Commands that
 * drive undo/redo themselves ({@see ManagesOwnHistory}) bypass this entirely.
 */
final class CommandBus
{
    public function dispatch(BuilderState $state, Command $command): BuilderState
    {
        if ($command instanceof ManagesOwnHistory) {
            return $command->execute($state);
        }

        $before = $state->payload();
        $state = $command->execute($state);
        $after = $state->payload();

        if ($before !== $after) {
            $state->history()->snapshot($before, $command->label());
            $state->markDirty();
        }

        return $state;
    }
}
