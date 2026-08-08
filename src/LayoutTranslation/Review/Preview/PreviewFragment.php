<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Review\Preview;

use Plugins\PageBuilder\LayoutTranslation\Diff\NodeChangeType;

/**
 * The render-only fragments for one origin (LT-8B, per LT-8A-REVIEW-WORKSPACE-ARCHITECTURE §6).
 *
 * Layer: Translation (review preview / value). The single content-bearing value in the Review layer. It
 * is presentation-only and one-directional (INV-LT-8A-02): consumed by the Conflict Panel renderer and
 * discarded — never re-entered into the plan, decisions, command, coordinator, or history. It exposes no
 * mutator (INV-LT-8A-08). The `change` is echoed from the plan item for LABELLING only; the authoritative
 * classification remains the plan's.
 */
final class PreviewFragment
{
    public function __construct(
        public readonly string $origin,
        public readonly NodeChangeType $change,
        public readonly PreviewAvailability $availability,
        public readonly ?PreviewSide $baseline,
        public readonly ?PreviewSide $source,
        public readonly ?PreviewSide $target,
    ) {}

    public static function available(string $origin, NodeChangeType $change, PreviewSide $baseline, PreviewSide $source, PreviewSide $target): self
    {
        return new self($origin, $change, PreviewAvailability::Available, $baseline, $source, $target);
    }

    public static function unavailable(string $origin, NodeChangeType $change, PreviewAvailability $availability): self
    {
        return new self($origin, $change, $availability, null, null, null);
    }

    public function isAvailable(): bool
    {
        return $this->availability === PreviewAvailability::Available;
    }
}
