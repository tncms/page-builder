<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Timeline\Explainability;

/**
 * One immutable, metadata-only explanation of a single synced item (LT-9B, per LT-9A §6).
 *
 * Layer: Translation (timeline / explainability). Carries origins and enums only — never content. The
 * `classification` and `conflictReason` are present in `Full` mode (re-derived via frozen LT-5/LT-6) and
 * null in `MetadataOnly` mode; `sourceChange` and `decision` always come straight from history.
 */
final class ExplainabilityNode
{
    public function __construct(
        public readonly string $origin,
        public readonly string $sourceChange,
        public readonly ?string $classification,
        public readonly ?string $conflictReason,
        public readonly string $decision,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'origin' => $this->origin,
            'source_change' => $this->sourceChange,
            'classification' => $this->classification,
            'conflict_reason' => $this->conflictReason,
            'decision' => $this->decision,
        ];
    }
}
