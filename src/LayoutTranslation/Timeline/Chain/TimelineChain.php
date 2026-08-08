<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Timeline\Chain;

/**
 * The baseline-continuity model over an ordered entry list (LT-9B, per INV-LT-9-TL-06).
 *
 * Layer: Translation (timeline / value). Immutable. Holds the gaps detected across the chain; a continuous
 * chain has none. Chain detection is performed by the builder; this value only reports it.
 */
final class TimelineChain
{
    /**
     * @param  array<int, TimelineGap>  $gaps
     */
    private function __construct(
        private readonly array $gaps,
    ) {}

    /**
     * @param  array<int, TimelineGap>  $gaps
     */
    public static function of(array $gaps): self
    {
        return new self(array_values($gaps));
    }

    /**
     * @return array<int, TimelineGap>
     */
    public function gaps(): array
    {
        return $this->gaps;
    }

    public function isContinuous(): bool
    {
        return $this->gaps === [];
    }

    public function gapCount(): int
    {
        return count($this->gaps);
    }
}
