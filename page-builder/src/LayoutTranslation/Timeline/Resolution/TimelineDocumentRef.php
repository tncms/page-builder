<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Timeline\Resolution;

use Plugins\PageBuilder\LayoutTranslation\Timeline\Entry\TimelineEntryId;

/**
 * An immutable request for one timeline document (LT-9B, per LT-9A §7).
 *
 * Layer: Translation (timeline / resolution). Carries the ROLE plus exactly one addressing mode: a
 * content-addressed `reference` (a `BaselineFingerprint` value) for baseline/source documents, or an
 * `entryId` for target documents indexed by history id. It never carries a blob identity (`ContentDigest`);
 * conflating reference and blob identity is prohibited (INV-LT-9-TL-05).
 */
final class TimelineDocumentRef
{
    private function __construct(
        public readonly TimelineDocumentRole $role,
        public readonly ?string $reference,
        public readonly ?TimelineEntryId $entryId,
    ) {}

    public static function byReference(TimelineDocumentRole $role, string $reference): self
    {
        return new self($role, $reference, null);
    }

    public static function byEntry(TimelineDocumentRole $role, TimelineEntryId $entryId): self
    {
        return new self($role, null, $entryId);
    }

    public function isReferenceBased(): bool
    {
        return $this->reference !== null;
    }
}
