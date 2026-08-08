<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Timeline\Projection;

use Plugins\PageBuilder\LayoutTranslation\Timeline\Entry\TimelineEntryId;

/**
 * An immutable set of selected timeline entry ids (LT-9B, per INV-LT-9B-RT-03).
 *
 * Layer: Translation (timeline / projection). Selection composes immutably — `with`/`without` return a NEW
 * selection. Applying it yields a NEW projection.
 */
final class TimelineSelection
{
    /** @var array<int, true> */
    private readonly array $ids;

    /**
     * @param  array<int, TimelineEntryId>  $ids
     */
    private function __construct(array $ids)
    {
        $map = [];
        foreach ($ids as $id) {
            $map[$id->value] = true;
        }
        ksort($map);
        $this->ids = $map;
    }

    public static function none(): self
    {
        return new self([]);
    }

    /**
     * @param  array<int, TimelineEntryId>  $ids
     */
    public static function of(array $ids): self
    {
        return new self($ids);
    }

    public function with(TimelineEntryId $id): self
    {
        $current = array_map(static fn (int $v): TimelineEntryId => TimelineEntryId::of($v), array_keys($this->ids));
        $current[] = $id;

        return new self($current);
    }

    public function contains(TimelineEntryId $id): bool
    {
        return isset($this->ids[$id->value]);
    }

    /**
     * @return array<int, int>
     */
    public function ids(): array
    {
        return array_keys($this->ids);
    }

    public function count(): int
    {
        return count($this->ids);
    }
}
