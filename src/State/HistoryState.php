<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\State;

/**
 * Undo/redo history of layout snapshots (Phase 8).
 *
 * The {@see CommandBus} snapshots the pre-mutation payload before a command that
 * changes the layout; undo/redo swap the current payload against these snapshots.
 * Snapshots are payload arrays only — never DB records — capped to a bounded
 * stack so long editing sessions stay memory-safe. Consecutive duplicate
 * snapshots are ignored so no-op or idempotent commands don't inflate history.
 */
final class HistoryState
{
    public const DEFAULT_LIMIT = 50;

    /** @var array<int, array{payload: array<string, mixed>, label: string|null}> */
    private array $undo = [];

    /** @var array<int, array{payload: array<string, mixed>, label: string|null}> */
    private array $redo = [];

    public function __construct(private readonly int $limit = self::DEFAULT_LIMIT) {}

    /**
     * Push a snapshot of $payload onto the undo stack and clear the redo stack.
     * Ignored when identical to the most recent snapshot.
     *
     * @param  array<string, mixed>  $payload
     */
    public function snapshot(array $payload, ?string $label = null): static
    {
        $last = end($this->undo);
        if ($last !== false && $last['payload'] === $payload) {
            return $this;
        }

        $this->undo[] = ['payload' => $payload, 'label' => $label];

        while (count($this->undo) > $this->limit) {
            array_shift($this->undo);
        }

        $this->redo = [];

        return $this;
    }

    public function canUndo(): bool
    {
        return $this->undo !== [];
    }

    public function canRedo(): bool
    {
        return $this->redo !== [];
    }

    /**
     * Undo: pop the most recent snapshot and return it, pushing $currentPayload
     * onto the redo stack. Returns null when there is nothing to undo.
     *
     * @param  array<string, mixed>  $currentPayload
     * @return array<string, mixed>|null
     */
    public function undo(array $currentPayload): ?array
    {
        if ($this->undo === []) {
            return null;
        }

        $entry = array_pop($this->undo);
        $this->redo[] = ['payload' => $currentPayload, 'label' => $entry['label']];

        return $entry['payload'];
    }

    /**
     * Redo: pop the most recent redo snapshot and return it, pushing
     * $currentPayload back onto the undo stack. Null when nothing to redo.
     *
     * @param  array<string, mixed>  $currentPayload
     * @return array<string, mixed>|null
     */
    public function redo(array $currentPayload): ?array
    {
        if ($this->redo === []) {
            return null;
        }

        $entry = array_pop($this->redo);
        $this->undo[] = ['payload' => $currentPayload, 'label' => $entry['label']];

        return $entry['payload'];
    }

    public function clear(): static
    {
        $this->undo = [];
        $this->redo = [];

        return $this;
    }

    public function limit(): int
    {
        return $this->limit;
    }

    /**
     * @return array{can_undo: bool, can_redo: bool, undo_count: int, redo_count: int, limit: int, undo_labels: array<int, string|null>, redo_labels: array<int, string|null>}
     */
    public function toArray(): array
    {
        return [
            'can_undo' => $this->canUndo(),
            'can_redo' => $this->canRedo(),
            'undo_count' => count($this->undo),
            'redo_count' => count($this->redo),
            'limit' => $this->limit,
            'undo_labels' => array_map(static fn (array $e): ?string => $e['label'], $this->undo),
            'redo_labels' => array_map(static fn (array $e): ?string => $e['label'], $this->redo),
        ];
    }
}
