<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\State;

/**
 * The editor's selection: which component is selected, which is hovered, and the
 * active inspector panel/tab (Phase 8). Pure UI state — it holds no layout data
 * and mutating it never touches the payload.
 */
final class SelectionState
{
    public function __construct(
        private ?string $selected = null,
        private ?string $hovered = null,
        private ?string $panel = null,
    ) {}

    public function select(?string $id): static
    {
        $this->selected = $id;

        return $this;
    }

    public function hover(?string $id): static
    {
        $this->hovered = $id;

        return $this;
    }

    public function usePanel(?string $panel): static
    {
        $this->panel = $panel;

        return $this;
    }

    public function selected(): ?string
    {
        return $this->selected;
    }

    public function hovered(): ?string
    {
        return $this->hovered;
    }

    public function panel(): ?string
    {
        return $this->panel;
    }

    public function clear(): static
    {
        $this->selected = null;
        $this->hovered = null;
        $this->panel = null;

        return $this;
    }

    /**
     * @return array{selected: string|null, hovered: string|null, panel: string|null}
     */
    public function toArray(): array
    {
        return [
            'selected' => $this->selected,
            'hovered' => $this->hovered,
            'panel' => $this->panel,
        ];
    }
}
