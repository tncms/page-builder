<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Review\Navigation;

/**
 * The currently-focused origin (LT-8B, per LT-8A-REVIEW-WORKSPACE-ARCHITECTURE §2/§3).
 *
 * Layer: Translation (review navigation / ephemeral state). Selection is DISPOSABLE presentation state,
 * not a source of truth (INV-LT-8A-07): it may be lost and re-derived (default to the first node) with no
 * loss of authoritative state. It holds only an origin string — never content, never a decision. It is a
 * pointer into the projection, never a mutator of it (INV-LT-8A-08).
 */
final class SelectionModel
{
    private function __construct(
        private ?string $current,
    ) {}

    public static function empty(): self
    {
        return new self(null);
    }

    public static function at(string $origin): self
    {
        return new self($origin);
    }

    public function select(string $origin): void
    {
        $this->current = $origin;
    }

    public function clear(): void
    {
        $this->current = null;
    }

    public function current(): ?string
    {
        return $this->current;
    }

    public function hasSelection(): bool
    {
        return $this->current !== null;
    }
}
