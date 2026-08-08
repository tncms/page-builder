<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\State;

use Carbon\CarbonImmutable;

/**
 * Tracks whether the editor has unsaved changes and the last change/save times
 * (Phase 8). The {@see CommandBus} marks the state dirty after any command that
 * changes the layout; the persistence layer (a later phase) marks it clean.
 */
final class DirtyState
{
    private bool $dirty = false;

    private ?CarbonImmutable $lastChangedAt = null;

    private ?CarbonImmutable $lastSavedAt = null;

    public function markDirty(): static
    {
        $this->dirty = true;
        $this->lastChangedAt = CarbonImmutable::now();

        return $this;
    }

    public function markClean(): static
    {
        $this->dirty = false;
        $this->lastSavedAt = CarbonImmutable::now();

        return $this;
    }

    public function isDirty(): bool
    {
        return $this->dirty;
    }

    public function lastChangedAt(): ?CarbonImmutable
    {
        return $this->lastChangedAt;
    }

    public function lastSavedAt(): ?CarbonImmutable
    {
        return $this->lastSavedAt;
    }

    /**
     * @return array{dirty: bool, last_changed_at: string|null, last_saved_at: string|null}
     */
    public function toArray(): array
    {
        return [
            'dirty' => $this->dirty,
            'last_changed_at' => $this->lastChangedAt?->toIso8601String(),
            'last_saved_at' => $this->lastSavedAt?->toIso8601String(),
        ];
    }
}
