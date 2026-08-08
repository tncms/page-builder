<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\State\Commands;

use Plugins\PageBuilder\State\BuilderState;

/**
 * A single editor intent (Phase 8).
 *
 * Commands are the ONLY way the editor mutates a {@see BuilderState}: the future
 * Vue layer sends a command name + payload, the {@see \Plugins\PageBuilder\State\CommandBus}
 * runs it, snapshots history and marks dirty. A command must be pure with
 * respect to persistence — it changes state, never the database.
 */
interface Command
{
    /** Stable machine name (e.g. "add-section"), used for dispatch + logging. */
    public function name(): string;

    /** Human-readable label, surfaced as the history entry description. */
    public function label(): string;

    /** Apply this command to the state and return it. */
    public function execute(BuilderState $state): BuilderState;

    /**
     * The command's serialized parameters (for the API / transcript).
     *
     * @return array<string, mixed>
     */
    public function payload(): array;
}
