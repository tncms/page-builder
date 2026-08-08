<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Review\Session;

use InvalidArgumentException;

/**
 * The identity of a review session (LT-8B, per LT-8A-REVIEW-WORKSPACE-ARCHITECTURE §2).
 *
 * Layer: Translation (review session / value). An opaque, immutable id used only to correlate a paused
 * session with its snapshot in the ephemeral store. It carries no authority — the authoritative state is
 * always (plan, decisions) (INV-LT-8A-07). Generation is infrastructure; this VO only wraps a value.
 */
final class ReviewSessionId
{
    private function __construct(
        public readonly string $value,
    ) {}

    public static function of(string $value): self
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            throw new InvalidArgumentException('A review session id must be a non-empty string.');
        }

        return new self($trimmed);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
