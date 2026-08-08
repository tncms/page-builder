<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\State\Commands;

/**
 * Marker for commands that manage the undo/redo stacks themselves (Undo/Redo).
 *
 * The {@see \Plugins\PageBuilder\State\CommandBus} skips its own snapshot-and-mark
 * logic for these — otherwise undoing would itself push a new history entry.
 */
interface ManagesOwnHistory
{
}
