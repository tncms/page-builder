<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\TimelineInfrastructure;

use Plugins\PageBuilder\LayoutTranslation\Timeline\Resolution\TimelineDocumentRef;

/**
 * The infrastructure seam to a snapshot store (LT-9C).
 *
 * Layer: Translation (timeline infrastructure). The {@see SnapshotTimelineDocumentResolverAdapter} resolves
 * timeline documents through this backend. Its production implementation bridges the frozen LT-8.5 snapshot
 * capability; until that capability is implemented, the {@see NullTimelineSnapshotBackend} default returns
 * null and replay/explainability degrade fail-soft (INV-LT-9C-04). Read-only.
 */
interface TimelineSnapshotBackendInterface
{
    /**
     * @return array<string, mixed>|null  the immutable document, or null when unavailable
     */
    public function fetch(TimelineDocumentRef $ref): ?array;
}
