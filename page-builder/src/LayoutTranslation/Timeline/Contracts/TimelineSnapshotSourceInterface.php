<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Timeline\Contracts;

use Plugins\PageBuilder\LayoutTranslation\Timeline\Resolution\TimelineDocumentRef;

/**
 * The timeline's read seam over immutable snapshots (LT-9B, per LT-9A §7).
 *
 * Layer: Translation (timeline / read port). The single document-resolution path — a wiring adapter bridges
 * it to the frozen LT-8.5 snapshot store (no snapshot redesign). Returns the immutable document array for a
 * request, or `null` when the snapshot is not retained (fail-soft — INV-LT-9-TL-03). It never mutates a
 * snapshot and never exposes blob identity.
 */
interface TimelineSnapshotSourceInterface
{
    /**
     * @return array<string, mixed>|null  the immutable document, or null when unavailable
     */
    public function document(TimelineDocumentRef $ref): ?array;
}
