<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Repository;

use Countable;
use IteratorAggregate;
use Traversable;

/**
 * An immutable, ordered collection of Design Resources keyed by RID.
 *
 * Operations return new collections; the underlying set is never mutated.
 * Iteration order is deterministic (the order in which resources were provided,
 * which the repository sorts explicitly).
 *
 * @implements IteratorAggregate<int, DesignResource>
 */
final class ResourceCollection implements Countable, IteratorAggregate
{
    /** @var array<int, DesignResource> */
    private readonly array $items;

    /**
     * @param  iterable<DesignResource>  $items
     */
    public function __construct(iterable $items = [])
    {
        $list = [];
        foreach ($items as $item) {
            $list[] = $item;
        }
        $this->items = $list;
    }

    /**
     * @return array<int, DesignResource>
     */
    public function all(): array
    {
        return $this->items;
    }

    public function first(): ?DesignResource
    {
        return $this->items[0] ?? null;
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    public function count(): int
    {
        return count($this->items);
    }

    /**
     * @return array<int, string>
     */
    public function rids(): array
    {
        return array_map(static fn (DesignResource $r): string => $r->rid, $this->items);
    }

    /**
     * @param  callable(DesignResource): bool  $predicate
     */
    public function filter(callable $predicate): self
    {
        return new self(array_values(array_filter($this->items, $predicate)));
    }

    /**
     * @param  callable(DesignResource): mixed  $mapper
     * @return array<int, mixed>
     */
    public function map(callable $mapper): array
    {
        return array_map($mapper, $this->items);
    }

    /**
     * @param  callable(DesignResource, DesignResource): int  $comparator
     */
    public function sort(callable $comparator): self
    {
        $items = $this->items;
        usort($items, $comparator);

        return new self($items);
    }

    public function getIterator(): Traversable
    {
        return new \ArrayIterator($this->items);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function toMetadataArray(): array
    {
        return array_map(static fn (DesignResource $r): array => $r->toMetadata(), $this->items);
    }
}
