<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Execution\Loading;

/**
 * The canonical persisted state a synchronization execution reads (LT-7B3, per
 * LT-7-EXECUTION-ARCHITECTURE §2).
 *
 * Layer: Translation (execution loading / value). Immutable snapshot returned by the state loader: the
 * binding presence, the prior baseline fingerprint, the current source and target payloads, the source
 * layout id, and the target owner coordinates for persistence. The coordinator composes this; the
 * mutation engine never sees it (INV-LT-7-OPS-02).
 */
final class LoadedSynchronizationState
{
    /**
     * @param  array<string, mixed>|null  $sourcePayload
     * @param  array<string, mixed>|null  $targetPayload
     */
    public function __construct(
        private readonly bool $bound,
        private readonly ?string $baselineFingerprint,
        private readonly ?array $sourcePayload,
        private readonly ?array $targetPayload,
        private readonly ?int $sourceLayoutId,
        private readonly ?string $ownerType,
        private readonly int|string|null $ownerId,
    ) {}

    public static function unbound(): self
    {
        return new self(false, null, null, null, null, null, null);
    }

    public function isBound(): bool
    {
        return $this->bound;
    }

    public function hasBaseline(): bool
    {
        return is_string($this->baselineFingerprint) && $this->baselineFingerprint !== '';
    }

    public function hasSource(): bool
    {
        return $this->sourcePayload !== null;
    }

    public function hasTarget(): bool
    {
        return $this->targetPayload !== null;
    }

    public function baselineFingerprint(): ?string
    {
        return $this->baselineFingerprint;
    }

    /**
     * @return array<string, mixed>
     */
    public function sourcePayload(): array
    {
        return $this->sourcePayload ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function targetPayload(): array
    {
        return $this->targetPayload ?? [];
    }

    public function sourceLayoutId(): ?int
    {
        return $this->sourceLayoutId;
    }

    public function ownerType(): ?string
    {
        return $this->ownerType;
    }

    public function ownerId(): int|string|null
    {
        return $this->ownerId;
    }
}
