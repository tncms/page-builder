<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\State\Commands;

use Plugins\PageBuilder\State\BuilderState;

/**
 * Redo the last undone command by restoring the next snapshot.
 *
 * Implements {@see ManagesOwnHistory} so the CommandBus does not snapshot again.
 */
final class RedoCommand extends AbstractCommand implements ManagesOwnHistory
{
    public function name(): string
    {
        return 'redo';
    }

    public function label(): string
    {
        return 'Redo';
    }

    public function execute(BuilderState $state): BuilderState
    {
        $next = $state->history()->redo($state->payload());

        if ($next === null) {
            return $state;
        }

        return $state->setPayload($next)->markDirty();
    }
}
