<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Persistence\Results;

/**
 * The immutable outcome of a backfill/reconciliation run — LT-2.
 *
 * Layer: Persistence / Result. Diagnostic tally of an additive, fail-closed
 * backfill: how many pb_layouts rows were scanned, bound, skipped (already bound
 * or no layout document), or left unresolved because a canonical identity could
 * not be established (never fabricated). Unresolved records are reported, never
 * force-created.
 */
final class BindingReconciliationReport
{
    /**
     * @param  array<int, array<string, mixed>>  $unresolvedRecords
     */
    public function __construct(
        public readonly int $scanned,
        public readonly int $created,
        public readonly int $skipped,
        public readonly int $unresolved,
        public readonly int $conflicts,
        public readonly array $unresolvedRecords = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'scanned' => $this->scanned,
            'created' => $this->created,
            'skipped' => $this->skipped,
            'unresolved' => $this->unresolved,
            'conflicts' => $this->conflicts,
            'unresolved_records' => $this->unresolvedRecords,
        ];
    }
}
