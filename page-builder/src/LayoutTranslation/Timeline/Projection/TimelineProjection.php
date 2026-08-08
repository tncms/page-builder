<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Timeline\Projection;

use Plugins\PageBuilder\LayoutTranslation\Timeline\Entry\TimelineEntry;

/**
 * An immutable, ordered view of a subset of timeline entries (LT-9B, per INV-LT-9B-RT-03).
 *
 * Layer: Translation (timeline / projection). Produced by filtering or selection; a projection is never
 * mutated — every refinement yields a NEW projection. Read-only and payload-safe.
 */
final class TimelineProjection
{
    /**
     * @param  array<int, TimelineEntry>  $entries
     */
    private function __construct(
        private readonly string $pageTranslationKey,
        private readonly array $entries,
    ) {}

    /**
     * @param  array<int, TimelineEntry>  $entries
     */
    public static function of(string $pageTranslationKey, array $entries): self
    {
        return new self($pageTranslationKey, array_values($entries));
    }

    public function pageTranslationKey(): string
    {
        return $this->pageTranslationKey;
    }

    /**
     * @return array<int, TimelineEntry>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    /**
     * @return array<int, int>
     */
    public function ids(): array
    {
        return array_map(static fn (TimelineEntry $e): int => $e->id->value, $this->entries);
    }

    public function count(): int
    {
        return count($this->entries);
    }

    public function isEmpty(): bool
    {
        return $this->entries === [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function toArray(): array
    {
        return array_map(static fn (TimelineEntry $e): array => $e->toArray(), $this->entries);
    }
}
