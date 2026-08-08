<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\State\Commands;

use Plugins\PageBuilder\State\BuilderState;

/**
 * Move a component under a new parent (or reorder a section at the root when the
 * target parent is null) at a given index.
 */
final class MoveComponentCommand extends AbstractCommand
{
    public function __construct(
        private readonly string $componentId,
        private readonly ?string $targetParentId,
        private readonly int $index,
    ) {}

    public function name(): string
    {
        return 'move-component';
    }

    public function label(): string
    {
        return 'Move component';
    }

    public function execute(BuilderState $state): BuilderState
    {
        return $state->setPayload(
            $this->mutator()->moveComponent($state->payload(), $this->componentId, $this->targetParentId, $this->index),
        );
    }

    /**
     * @return array{component_id: string, target_parent_id: string|null, index: int}
     */
    public function payload(): array
    {
        return [
            'component_id' => $this->componentId,
            'target_parent_id' => $this->targetParentId,
            'index' => $this->index,
        ];
    }
}
