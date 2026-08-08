<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Baseline\Results;

use Plugins\PageBuilder\LayoutTranslation\Baseline\LayoutBaseline;

/**
 * The immutable, typed result of a baseline capture (LT-4).
 *
 * Layer: Translation (baseline / result). Exposes only safe domain information —
 * the typed outcome and, on success, the persisted baseline. Never exposes models,
 * raw exceptions, SQL or rows.
 */
final class BaselineCaptureResult
{
    private function __construct(
        public readonly BaselineCaptureOutcome $outcome,
        public readonly ?LayoutBaseline $baseline,
        public readonly ?string $detail,
    ) {}

    public static function captured(LayoutBaseline $baseline): self
    {
        return new self(BaselineCaptureOutcome::Captured, $baseline, null);
    }

    public static function failure(BaselineCaptureOutcome $outcome, ?string $detail = null): self
    {
        return new self($outcome, null, $detail);
    }

    public function outcome(): BaselineCaptureOutcome
    {
        return $this->outcome;
    }

    public function baseline(): ?LayoutBaseline
    {
        return $this->baseline;
    }

    public function isCaptured(): bool
    {
        return $this->outcome === BaselineCaptureOutcome::Captured;
    }
}
