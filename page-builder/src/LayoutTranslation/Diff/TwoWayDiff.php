<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Diff;

/**
 * An immutable, deterministic two-way structural diff (LT-5).
 *
 * Layer: Translation (diff / value). Holds the non-`Unchanged` node changes between
 * a `from` and a `to` layout, sorted by origin. Read-only.
 */
final class TwoWayDiff
{
    /**
     * @param  array<int, NodeChange>  $changes  sorted by origin, excludes Unchanged
     */
    public function __construct(private readonly array $changes) {}

    /**
     * @return array<int, NodeChange>
     */
    public function changes(): array
    {
        return $this->changes;
    }

    public function isEmpty(): bool
    {
        return $this->changes === [];
    }

    public function typeFor(string $origin): ?NodeChangeType
    {
        foreach ($this->changes as $change) {
            if ($change->origin === $origin) {
                return $change->type;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    public function changedOrigins(): array
    {
        return array_map(static fn (NodeChange $c): string => $c->origin, $this->changes);
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function toArray(): array
    {
        return array_map(static fn (NodeChange $c): array => $c->toArray(), $this->changes);
    }
}
