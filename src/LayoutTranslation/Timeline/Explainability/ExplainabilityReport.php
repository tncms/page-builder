<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Timeline\Explainability;

use Plugins\PageBuilder\LayoutTranslation\Timeline\Entry\TimelineEntryId;

/**
 * The immutable, metadata-only explanation of one timeline entry (LT-9B, per LT-9A §6).
 *
 * Layer: Translation (timeline / explainability). An entry id, the {@see ExplanationMode}, and one
 * {@see ExplainabilityNode} per recorded item. Payload-safe — it re-injects nothing upstream and carries no
 * document content (INV-LT-9-TL-04).
 */
final class ExplainabilityReport
{
    /**
     * @param  array<int, ExplainabilityNode>  $nodes
     */
    public function __construct(
        public readonly TimelineEntryId $entryId,
        public readonly ExplanationMode $mode,
        public readonly array $nodes,
    ) {}

    public function isFull(): bool
    {
        return $this->mode === ExplanationMode::Full;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'entry_id' => $this->entryId->value,
            'mode' => $this->mode->value,
            'nodes' => array_map(static fn (ExplainabilityNode $n): array => $n->toArray(), $this->nodes),
        ];
    }
}
