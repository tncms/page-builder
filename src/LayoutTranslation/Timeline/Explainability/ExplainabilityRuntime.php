<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Timeline\Explainability;

use Plugins\PageBuilder\LayoutTranslation\Diff\StructuralDiffEngine;
use Plugins\PageBuilder\LayoutTranslation\Plan\SynchronizationPlanItem;
use Plugins\PageBuilder\LayoutTranslation\Plan\SynchronizationPlanner;
use Plugins\PageBuilder\LayoutTranslation\Timeline\Entry\TimelineEntry;
use Plugins\PageBuilder\LayoutTranslation\Timeline\Resolution\TimelineDocumentResolver;

/**
 * The read-only explainability runtime (LT-9B, per LT-9A §6, INV-LT-9-TL-04).
 *
 * Layer: Translation (timeline / explainability). Explains *why* a sync did what it did, reconstructively:
 * from history it always has the origins, change types, and decisions; when snapshots are available it
 * re-derives each item's classification and conflict reason via the FROZEN LT-5 diff + LT-6 planner. It
 * degrades gracefully — `Full` with snapshots, `MetadataOnly` without — and is metadata-only: it resolves
 * documents solely to re-derive classification and re-injects nothing upstream. It mutates nothing.
 */
final class ExplainabilityRuntime
{
    public function __construct(
        private readonly TimelineDocumentResolver $documents,
        private readonly StructuralDiffEngine $diff,
        private readonly SynchronizationPlanner $planner,
    ) {}

    public function explain(TimelineEntry $entry): ExplainabilityReport
    {
        $baseline = $this->documents->baselineBefore($entry);
        $source = $this->documents->source($entry);
        $targetBefore = $this->documents->targetBefore($entry);

        $classifications = null;
        if ($baseline !== null && $source !== null && $targetBefore !== null) {
            $classifications = $this->classify($baseline, $source, $targetBefore);
        }

        $nodes = [];
        foreach ([...$entry->appliedItems, ...$entry->skippedItems] as $item) {
            $origin = (string) ($item['origin'] ?? '');
            if ($origin === '') {
                continue;
            }

            $planItem = $classifications[$origin] ?? null;

            $nodes[] = new ExplainabilityNode(
                $origin,
                (string) ($item['source_change'] ?? ''),
                $planItem?->classification->value,
                $planItem?->conflictReason?->value,
                (string) ($item['decision'] ?? ''),
            );
        }

        return new ExplainabilityReport(
            $entry->id,
            $classifications === null ? ExplanationMode::MetadataOnly : ExplanationMode::Full,
            $nodes,
        );
    }

    /**
     * @param  array<string, mixed>  $baseline
     * @param  array<string, mixed>  $source
     * @param  array<string, mixed>  $target
     * @return array<string, SynchronizationPlanItem>
     */
    private function classify(array $baseline, array $source, array $target): array
    {
        $plan = $this->planner->plan($this->diff->threeWay($baseline, $source, $target));

        $byOrigin = [];
        foreach ($plan->items() as $item) {
            $byOrigin[$item->origin] = $item;
        }

        return $byOrigin;
    }
}
