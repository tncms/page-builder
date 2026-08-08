<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Timeline\Entry;

use InvalidArgumentException;

/**
 * The identity of a timeline entry — the committed history row id (LT-9B).
 *
 * Layer: Translation (timeline / value). Immutable, framework-free. Wraps the append-only history id so the
 * runtime references entries by identity, never by array position.
 */
final class TimelineEntryId
{
    private function __construct(
        public readonly int $value,
    ) {}

    public static function of(int $value): self
    {
        if ($value <= 0) {
            throw new InvalidArgumentException('A timeline entry id must be a positive integer.');
        }

        return new self($value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }
}
