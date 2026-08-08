<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\State\Commands;

use Plugins\PageBuilder\State\BuilderState;

/**
 * Select a component (or clear selection with null). Selection-only: it does not
 * change the payload, so the CommandBus records no history and no dirty flag.
 */
final class SelectComponentCommand extends AbstractCommand
{
    public function __construct(private readonly ?string $componentId) {}

    public function name(): string
    {
        return 'select-component';
    }

    public function label(): string
    {
        return 'Select component';
    }

    public function execute(BuilderState $state): BuilderState
    {
        return $state->select($this->componentId);
    }

    /**
     * @return array{component_id: string|null}
     */
    public function payload(): array
    {
        return ['component_id' => $this->componentId];
    }
}
