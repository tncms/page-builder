<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\State\Commands;

use Plugins\PageBuilder\State\BuilderState;
use Plugins\PageBuilder\State\ComponentId;

/**
 * Insert an explicit component payload into a target parent (or the root when
 * the target parent is null) at an optional index, with fresh ids so it never
 * collides with an existing node. Selects the inserted component.
 *
 * Unlike {@see PasteComponentCommand} (which reads the server-side clipboard),
 * this command carries the component in its own payload, so it works across the
 * stateless command API where no clipboard survives between requests — the
 * editor keeps the clipboard client-side and sends the node here to paste.
 */
final class InsertComponentCommand extends AbstractCommand
{
    /**
     * @param  array<string, mixed>  $component
     */
    public function __construct(
        private readonly array $component,
        private readonly ?string $targetParentId = null,
        private readonly ?int $index = null,
    ) {}

    public function name(): string
    {
        return 'insert-component';
    }

    public function label(): string
    {
        return 'Insert component';
    }

    public function execute(BuilderState $state): BuilderState
    {
        if ($this->component === []) {
            return $state;
        }

        $kind = $this->targetParentId === null ? ComponentId::KIND_SECTION : ComponentId::KIND_BLOCK;
        $component = ComponentId::regenerate($this->component, $kind);

        $state->setPayload(
            $this->mutator()->insertComponent($state->payload(), $component, $this->targetParentId, $this->index),
        );

        return $state->select(is_string($component['id'] ?? null) ? $component['id'] : null);
    }

    /**
     * @return array{component: array<string, mixed>, target_parent_id: string|null, index: int|null}
     */
    public function payload(): array
    {
        return [
            'component' => $this->component,
            'target_parent_id' => $this->targetParentId,
            'index' => $this->index,
        ];
    }
}
