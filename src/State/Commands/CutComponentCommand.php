<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\State\Commands;

use Plugins\PageBuilder\State\BuilderState;

/**
 * Cut a component: copy it to the clipboard (cut mode) then remove it from the
 * layout. Removing changes the payload, so the CommandBus snapshots and marks
 * dirty. Clears selection if the cut component was selected.
 */
final class CutComponentCommand extends AbstractCommand
{
    public function __construct(private readonly string $componentId) {}

    public function name(): string
    {
        return 'cut-component';
    }

    public function label(): string
    {
        return 'Cut component';
    }

    public function execute(BuilderState $state): BuilderState
    {
        $found = $this->locator()->find($state->payload(), $this->componentId);

        if ($found === null) {
            return $state;
        }

        $state->clipboard()->cut($found['node']);
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
