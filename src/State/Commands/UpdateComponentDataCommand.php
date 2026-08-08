<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\State\Commands;

use Plugins\PageBuilder\State\BuilderState;

/**
 * Shallow-merge new values into a component's `data` map.
 */
final class UpdateComponentDataCommand extends AbstractCommand
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        private readonly string $componentId,
        private readonly array $data,
    ) {}

    public function name(): string
    {
        return 'update-component-data';
    }

    public function label(): string
    {
        return 'Update component';
    }

    public function execute(BuilderState $state): BuilderState
    {
        return $state->setPayload(
            $this->mutator()->updateComponentData($state->payload(), $this->componentId, $this->data),
        );
    }

    /**
     * @return array{component_id: string, data: array<string, mixed>}
     */
    public function payload(): array
    {
        return ['component_id' => $this->componentId, 'data' => $this->data];
    }
}
