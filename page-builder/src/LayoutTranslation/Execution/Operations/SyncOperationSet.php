<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Execution\Operations;

use InvalidArgumentException;

/**
 * An immutable, deterministically-ordered set of execution operations (LT-7B1).
 *
 * Layer: Translation (execution contract). The complete, self-contained work list a future Mutation
 * Engine consumes (Target + SyncOperation[]). Operations are ordered by origin so identical inputs
 * yield a byte-identical application (matching the sorted LT-5/LT-6 output). Each origin appears at
 * most once — a document cannot receive two conflicting operations for the same node. Like every LT-7
 * contract it holds only pure data: no Source, Target, Baseline, repository, callback or closure.
 */
final class SyncOperationSet
{
    /** @var array<int, SyncOperation> */
    private readonly array $operations;

    /**
     * @param  array<int, SyncOperation>  $operations
     */
    public function __construct(array $operations)
    {
        $byOrigin = [];
        foreach ($operations as $operation) {
            if (isset($byOrigin[$operation->origin])) {
                throw new InvalidArgumentException("Duplicate operation for origin: {$operation->origin}");
            }
            $byOrigin[$operation->origin] = $operation;
        }

        ksort($byOrigin);

        $this->operations = array_values($byOrigin);
    }

    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * @return array<int, SyncOperation>
     */
    public function all(): array
    {
        return $this->operations;
    }

    /**
     * @return array<int, string>
     */
    public function origins(): array
    {
        return array_map(static fn (SyncOperation $o): string => $o->origin, $this->operations);
    }

    public function isEmpty(): bool
    {
        return $this->operations === [];
    }

    public function count(): int
    {
        return count($this->operations);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function toArray(): array
    {
        return array_map(static fn (SyncOperation $o): array => $o->toArray(), $this->operations);
    }
}
