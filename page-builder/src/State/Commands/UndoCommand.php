<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\State\Commands;

use Plugins\PageBuilder\State\BuilderState;

/**
 * Undo the last layout-changing command by restoring the previous snapshot.
 *
 * Implements {@see ManagesOwnHistory} so the CommandBus does not snapshot again.
 */
final class UndoCommand extends AbstractCommand implements ManagesOwnHistory
{
    public function name(): string
    {
        return 'undo';
    }

    public function label(): string
    {
        return 'Undo';
    }

    public function execute(BuilderState $state): BuilderState
    {
        $previous = $state->history()->undo($state->payload());

        if ($previous === null) {
            return $state;
        }

        return $state->setPayload($previous)->markDirty();
    }
}
