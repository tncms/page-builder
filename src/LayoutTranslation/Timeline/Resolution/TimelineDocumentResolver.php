<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Timeline\Resolution;

use Plugins\PageBuilder\LayoutTranslation\Timeline\Contracts\TimelineSnapshotSourceInterface;
use Plugins\PageBuilder\LayoutTranslation\Timeline\Entry\TimelineEntry;

/**
 * The single document-resolution path for the timeline (LT-9B, per LT-9A §7, INV-LT-9-TL-03/05).
 *
 * Layer: Translation (timeline / resolution). Maps an entry + role to a snapshot document through the
 * {@see TimelineSnapshotSourceInterface}. Baseline/source documents resolve by fingerprint **reference**
 * (`baselineBefore` / `baselineAfter`); target documents resolve by entry identity. Read-only and fail-soft:
 * a missing snapshot yields `null` (never fabricated). Consumers use this and nothing else to obtain
 * historical content.
 */
final class TimelineDocumentResolver
{
    public function __construct(
        private readonly TimelineSnapshotSourceInterface $snapshots,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function baselineBefore(TimelineEntry $entry): ?array
    {
        return $this->snapshots->document(
            TimelineDocumentRef::byReference(TimelineDocumentRole::BaselineBefore, $entry->baselineBefore),
        );
    }

    /**
     * The source document that was synced — content-addresses to `baseline_after`.
     *
     * @return array<string, mixed>|null
     */
    public function source(TimelineEntry $entry): ?array
    {
        return $this->snapshots->document(
            TimelineDocumentRef::byReference(TimelineDocumentRole::Source, $entry->baselineAfter),
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function targetBefore(TimelineEntry $entry): ?array
    {
        return $this->snapshots->document(
            TimelineDocumentRef::byEntry(TimelineDocumentRole::TargetBefore, $entry->id),
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function targetAfter(TimelineEntry $entry): ?array
    {
        return $this->snapshots->document(
            TimelineDocumentRef::byEntry(TimelineDocumentRole::TargetAfter, $entry->id),
        );
    }
}
