<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Timeline\Replay;

use Plugins\PageBuilder\LayoutTranslation\Timeline\Entry\TimelineEntryId;

/**
 * The immutable, typed result of a replay (LT-9B, per LT-9A §3).
 *
 * Layer: Translation (timeline / replay). Payload-safe: an entry id, a status, and (on divergence) a
 * {@see ReplayDifference} or (on unavailability) a non-sensitive detail. It never carries document content
 * and is produced only through named constructors.
 */
final class ReplayResult
{
    private function __construct(
        public readonly TimelineEntryId $entryId,
        public readonly ReplayStatus $status,
        public readonly ?ReplayDifference $difference,
        public readonly ?string $detail,
    ) {}

    public static function reproduced(TimelineEntryId $entryId): self
    {
        return new self($entryId, ReplayStatus::Reproduced, null, null);
    }

    public static function diverged(TimelineEntryId $entryId, ReplayDifference $difference): self
    {
        return new self($entryId, ReplayStatus::Diverged, $difference, null);
    }

    public static function unavailable(TimelineEntryId $entryId, string $detail): self
    {
        return new self($entryId, ReplayStatus::Unavailable, null, $detail);
    }

    public function isReproduced(): bool
    {
        return $this->status === ReplayStatus::Reproduced;
    }

    public function isDiverged(): bool
    {
        return $this->status === ReplayStatus::Diverged;
    }

    public function isUnavailable(): bool
    {
        return $this->status === ReplayStatus::Unavailable;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'entry_id' => $this->entryId->value,
            'status' => $this->status->value,
            'difference' => $this->difference?->toArray(),
            'detail' => $this->detail,
        ];
    }
}
