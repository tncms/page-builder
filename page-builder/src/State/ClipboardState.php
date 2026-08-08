<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\State;

/**
 * The editor clipboard: a single copied/cut component payload plus the mode that
 * produced it (Phase 8). It stores the raw component node only — pasting is the
 * {@see \Plugins\PageBuilder\State\LayoutMutator}'s job (fresh ids, re-nesting).
 */
final class ClipboardState
{
    public const MODE_COPY = 'copy';

    public const MODE_CUT = 'cut';

    /** @var array<string, mixed>|null */
    private ?array $payload = null;

    private ?string $mode = null;

    /**
     * @param  array<string, mixed>  $componentPayload
     */
    public function copy(array $componentPayload): static
    {
        $this->payload = $componentPayload;
        $this->mode = self::MODE_COPY;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $componentPayload
     */
    public function cut(array $componentPayload): static
    {
        $this->payload = $componentPayload;
        $this->mode = self::MODE_CUT;

        return $this;
    }

    public function has(): bool
    {
        return $this->payload !== null;
    }

    public function mode(): ?string
    {
        return $this->mode;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function payload(): ?array
    {
        return $this->payload;
    }

    public function clear(): static
    {
        $this->payload = null;
        $this->mode = null;

        return $this;
    }

    /**
     * @return array{mode: string|null, has: bool, payload: array<string, mixed>|null}
     */
    public function toArray(): array
    {
        return [
            'mode' => $this->mode,
            'has' => $this->has(),
            'payload' => $this->payload,
        ];
    }
}
