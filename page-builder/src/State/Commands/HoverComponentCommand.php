<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\State\Commands;

use Plugins\PageBuilder\State\BuilderState;

/**
 * Hover a component (or clear hover with null). Selection-only: no payload
 * change, so no history snapshot and no dirty flag.
 */
final class HoverComponentCommand extends AbstractCommand
{
    public function __construct(private readonly ?string $componentId) {}

    public function name(): string
    {
        return 'hover-component';
    }

    public function label(): string
    {
        return 'Hover component';
    }

    public function execute(BuilderState $state): BuilderState
    {
        return $state->hover($this->componentId);
    }

    /**
     * @return array{component_id: string|null}
     */
    public function payload(): array
    {
        return ['component_id' => $this->componentId];
    }
}
