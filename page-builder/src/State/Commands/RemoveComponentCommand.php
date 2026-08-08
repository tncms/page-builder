<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\State\Commands;

use Plugins\PageBuilder\State\BuilderState;

/**
 * Remove a component (section or block) by id. Clears the selection if the
 * removed component was selected.
 */
final class RemoveComponentCommand extends AbstractCommand
{
    public function __construct(private readonly string $componentId) {}

    public function name(): string
    {
        return 'remove-component';
    }

    public function label(): string
    {
        return 'Remove component';
    }

    public function execute(BuilderState $state): BuilderState
    {
        $state->setPayload($this->mutator()->removeComponent($state->payload(), $this->componentId));

        if ($state->selected() === $this->componentId) {
            $state->select(null);
        }

        return $state;
    }

    /**
     * @return array{component_id: string}
     */
    public function payload(): array
    {
        return ['component_id' => $this->componentId];
    }
}
