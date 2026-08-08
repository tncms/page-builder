<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Diff;

/**
 * The immutable three-way structural diff (LT-5, per frozen LT-0-ADR-001 D8).
 *
 * Layer: Translation (diff / value). Derived from baseline B, current source S, and
 * current target T: `sourceDiff` = B→S (new source changes), `targetDiff` = B→T
 * (target customizations), and `conflicts` = origins changed on BOTH sides. This is
 * a read-only comparison; deciding what to DO with it is the synchronization plan
 * (LT-6), not LT-5.
 */
final class ThreeWayStructuralDiff
{
    /**
     * @param  array<int, string>  $conflicts  origins changed in both source and target
     */
    public function __construct(
        public readonly TwoWayDiff $sourceDiff,
        public readonly TwoWayDiff $targetDiff,
        private readonly array $conflicts,
    ) {}

    public function sourceDiff(): TwoWayDiff
    {
        return $this->sourceDiff;
    }

    public function targetDiff(): TwoWayDiff
    {
        return $this->targetDiff;
    }

    /**
     * @return array<int, string>
     */
    public function conflicts(): array
    {
        return $this->conflicts;
    }

    public function hasConflicts(): bool
    {
        return $this->conflicts !== [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'source_diff' => $this->sourceDiff->toArray(),
            'target_diff' => $this->targetDiff->toArray(),
            'conflicts' => $this->conflicts,
        ];
    }
}
