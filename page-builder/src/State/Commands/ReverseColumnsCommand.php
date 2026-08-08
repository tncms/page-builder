<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\State\Commands;

use Plugins\PageBuilder\State\BuilderState;

/**
 * Reverse a container's column order (Phase 26.1).
 *
 * A structural reorder that stays inside the container, so it goes through the
 * CommandBus as a single command — one undo entry — rather than a data patch.
 */
final class ReverseColumnsCommand extends AbstractCommand
{
    public function __construct(
        private readonly string $containerId,
    ) {}

    public function name(): string
    {
        return 'reverse-columns';
    }

    public function label(): string
    {
        return 'Reverse columns';
    }

    public function execute(BuilderState $state): BuilderState
    {
        return $state->setPayload(
            $this->mutator()->reverseColumns($state->payload(), $this->containerId),
        );
    }

    /**
     * @return array{container_id: string}
     */
    public function payload(): array
    {
        return [
            'container_id' => $this->containerId,
        ];
    }
}
