<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\State\Transactions;

use Plugins\PageBuilder\State\Commands\Command;

/**
 * An ordered group of commands run as one unit (Phase 8).
 *
 * Grouped operations (e.g. "add a section then move a block into it") should
 * appear as a single step in undo history. The {@see TransactionManager}
 * executes the commands and produces one snapshot for the whole group.
 */
final class Transaction
{
    /** @var array<int, Command> */
    private array $commands = [];

    public function __construct(private readonly string $label = 'Transaction') {}

    public static function make(string $label = 'Transaction'): self
    {
        return new self($label);
    }

    public function add(Command $command): static
    {
        $this->commands[] = $command;

        return $this;
    }

    /**
     * @return array<int, Command>
     */
    public function commands(): array
    {
        return $this->commands;
    }

    public function label(): string
    {
        return $this->label;
    }
}
