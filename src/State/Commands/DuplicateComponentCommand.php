<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\State\Commands;

use Plugins\PageBuilder\State\BuilderState;

/**
 * Duplicate a component in place (fresh ids for the copy and its descendants).
 */
final class DuplicateComponentCommand extends AbstractCommand
{
    public function __construct(private readonly string $componentId) {}

    public function name(): string
    {
        return 'duplicate-component';
    }

    public function label(): string
    {
        return 'Duplicate component';
    }

    public function execute(BuilderState $state): BuilderState
    {
        return $state->setPayload(
            $this->mutator()->duplicateComponent($state->payload(), $this->componentId),
        );
    }

    /**
     * @return array{component_id: string}
     */
    public function payload(): array
    {
        return ['component_id' => $this->componentId];
    }
}
