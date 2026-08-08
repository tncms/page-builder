<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Import;

/**
 * A recovery snapshot of the target draft taken immediately before replacement.
 * If anything fails, the original draft is restored from (or simply never
 * mutated past) this snapshot. It holds the user's own draft — never package
 * internals.
 */
final class FullPageImportSnapshot
{
    /**
     * @param  array<string, mixed>|null  $payload  the draft as it was before import
     */
    public function __construct(
        public readonly string $ownerType,
        public readonly int|string $ownerId,
        public readonly ?array $payload,
        public readonly string $revision,
    ) {
    }

    /**
     * Metadata-only projection (no full payload) for API/logging.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'ownerType' => $this->ownerType,
            'ownerId' => $this->ownerId,
            'revision' => $this->revision,
            'hadDraft' => $this->payload !== null,
            'sectionCount' => is_array($this->payload['sections'] ?? null) ? count($this->payload['sections']) : 0,
        ];
    }
}
