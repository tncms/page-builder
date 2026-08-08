<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\State;

use Plugins\PageBuilder\Support\LayoutNormalizer;

/**
 * The editor's in-memory working state (Phase 8).
 *
 * BuilderState is the single object the future Vue editor drives — but never by
 * editing raw JSON. The flow is:
 *
 *   Vue Editor → CommandBus → BuilderState → LayoutMutator → (payload) → save
 *
 * It holds the current draft payload (always normalized, with ids assigned so
 * every component is addressable) plus selection, clipboard, history, dirty and
 * autosave sub-states. It is PURE state: it never saves to the database and
 * never renders Blade.
 */
final class BuilderState
{
    /** @var array<string, mixed> */
    private array $payload;

    public function __construct(
        ?array $payload = null,
        private readonly SelectionState $selection = new SelectionState(),
        private readonly ClipboardState $clipboard = new ClipboardState(),
        private readonly HistoryState $history = new HistoryState(),
        private readonly DirtyState $dirty = new DirtyState(),
        private readonly AutosaveState $autosave = new AutosaveState(),
        private readonly LayoutNormalizer $normalizer = new LayoutNormalizer(),
    ) {
        $this->payload = $this->prepare($payload);
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    public static function make(?array $payload = null): static
    {
        return new static($payload);
    }

    /**
     * The current normalized draft payload (with ids assigned).
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->payload;
    }

    /**
     * Replace the payload (re-normalized, ids assigned). Does NOT itself mark
     * dirty — that is the CommandBus's decision after a real change.
     *
     * @param  array<string, mixed>  $payload
     */
    public function setPayload(array $payload): static
    {
        $this->payload = $this->prepare($payload);

        return $this;
    }

    /**
     * The section nodes of the current payload.
     *
     * @return array<int, array<string, mixed>>
     */
    public function sections(): array
    {
        return is_array($this->payload['sections'] ?? null) ? $this->payload['sections'] : [];
    }

    public function selected(): ?string
    {
        return $this->selection->selected();
    }

    public function hovered(): ?string
    {
        return $this->selection->hovered();
    }

    public function select(?string $componentId): static
    {
        $this->selection->select($componentId);

        return $this;
    }

    public function hover(?string $componentId): static
    {
        $this->selection->hover($componentId);

        return $this;
    }

    public function selection(): SelectionState
    {
        return $this->selection;
    }

    public function clipboard(): ClipboardState
    {
        return $this->clipboard;
    }

    public function history(): HistoryState
    {
        return $this->history;
    }

    public function dirty(): DirtyState
    {
        return $this->dirty;
    }

    public function autosave(): AutosaveState
    {
        return $this->autosave;
    }

    public function markDirty(): static
    {
        $this->dirty->markDirty();

        return $this;
    }

    public function markClean(): static
    {
        $this->dirty->markClean();

        return $this;
    }

    /**
     * @return array{payload: array<string, mixed>, selection: array<string, mixed>, clipboard: array<string, mixed>, history: array<string, mixed>, dirty: array<string, mixed>, autosave: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'payload' => $this->payload,
            'selection' => $this->selection->toArray(),
            'clipboard' => $this->clipboard->toArray(),
            'history' => $this->history->toArray(),
            'dirty' => $this->dirty->toArray(),
            'autosave' => $this->autosave->toArray(),
        ];
    }

    /**
     * Normalize then ensure every component has a stable id.
     *
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>
     */
    private function prepare(?array $payload): array
    {
        return ComponentId::assign($this->normalizer->normalize($payload));
    }
}
