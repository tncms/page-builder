<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\TimelineInfrastructure;

use Plugins\PageBuilder\LayoutTranslation\Timeline\Contracts\TimelineSnapshotSourceInterface;
use Plugins\PageBuilder\LayoutTranslation\Timeline\Resolution\TimelineDocumentRef;

/**
 * Adapts a snapshot backend to the frozen LT-9B snapshot read port (LT-9C, per INV-LT-9C-02).
 *
 * Layer: Translation (timeline infrastructure). Implements {@see TimelineSnapshotSourceInterface} by
 * delegating to a {@see TimelineSnapshotBackendInterface}. It translates PERSISTENCE ONLY — it adds no
 * domain behavior, never mutates a snapshot, and never exposes blob identity. Fail-soft: an unavailable
 * document is null, which the runtime handles (replay `Unavailable`, explainability `MetadataOnly`).
 */
final class SnapshotTimelineDocumentResolverAdapter implements TimelineSnapshotSourceInterface
{
    public function __construct(
        private readonly TimelineSnapshotBackendInterface $backend,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function document(TimelineDocumentRef $ref): ?array
    {
        return $this->backend->fetch($ref);
    }
}
