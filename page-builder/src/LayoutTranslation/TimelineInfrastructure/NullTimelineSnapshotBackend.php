<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\TimelineInfrastructure;

use Plugins\PageBuilder\LayoutTranslation\Timeline\Resolution\TimelineDocumentRef;

/**
 * The fail-closed default snapshot backend (LT-9C).
 *
 * Layer: Translation (timeline infrastructure). Returns null for every request: the LT-8.5 snapshot store
 * is not implemented yet, so historical documents are not retained. With this default bound, replay reports
 * `Unavailable` and explainability reports `MetadataOnly` — the honest, fail-soft state (INV-LT-9C-04). A
 * later phase binds a real LT-8.5-backed backend in its place, with no runtime, presentation, or contract
 * change.
 */
final class NullTimelineSnapshotBackend implements TimelineSnapshotBackendInterface
{
    public function fetch(TimelineDocumentRef $ref): ?array
    {
        return null;
    }
}
