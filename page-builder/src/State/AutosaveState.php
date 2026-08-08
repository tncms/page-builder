<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\State;

use Carbon\CarbonImmutable;

/**
 * Autosave intent metadata (Phase 8).
 *
 * This phase only records that an autosave was requested and when — it does NOT
 * implement any debounced worker, queue or persistence. The future editor reads
 * this flag to decide when to flush a draft; the mechanism itself is deferred.
 */
final class AutosaveState
{
    private bool $requested = false;

    private ?CarbonImmutable $requestedAt = null;

    public function request(): static
    {
        $this->requested = true;
        $this->requestedAt = CarbonImmutable::now();

        return $this;
    }

    public function clear(): static
    {
        $this->requested = false;
        $this->requestedAt = null;

        return $this;
    }

    public function isRequested(): bool
    {
        return $this->requested;
    }

    public function requestedAt(): ?CarbonImmutable
    {
        return $this->requestedAt;
    }

    /**
     * @return array{requested: bool, requested_at: string|null}
     */
    public function toArray(): array
    {
        return [
            'requested' => $this->requested,
            'requested_at' => $this->requestedAt?->toIso8601String(),
        ];
    }
}
