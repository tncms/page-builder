<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\State\Commands;

use Plugins\PageBuilder\State\BuilderState;
use Plugins\PageBuilder\State\ComponentId;

/**
 * Paste the clipboard component into a target parent (or the root when the
 * target parent is null) at an optional index, with fresh ids so the paste never
 * collides with its source. Selects the pasted component. No-op when the
 * clipboard is empty.
 */
final class PasteComponentCommand extends AbstractCommand
{
    public function __construct(
        private readonly ?string $targetParentId = null,
        private readonly ?int $index = null,
    ) {}

    public function name(): string
    {
        return 'paste-component';
    }

    public function label(): string
    {
        return 'Paste component';
    }

    public function execute(BuilderState $state): BuilderState
    {
        if (! $state->clipboard()->has()) {
            return $state;
        }

        $kind = $this->targetParentId === null ? ComponentId::KIND_SECTION : ComponentId::KIND_BLOCK;
        $component = ComponentId::regenerate((array) $state->clipboard()->payload(), $kind);

        $state->setPayload(
            $this->mutator()->insertComponent($state->payload(), $component, $this->targetParentId, $this->index),
        );

        return $state->select(is_string($component['id'] ?? null) ? $component['id'] : null);
    }

    /**
     * @return array{target_parent_id: string|null, index: int|null}
     */
    public function payload(): array
    {
        return ['target_parent_id' => $this->targetParentId, 'index' => $this->index];
    }
}
