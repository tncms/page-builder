<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Management;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Plugins\PageBuilder\Capture\DesignPreviewGenerator;

/**
 * Builds a duplicate of a "My Designs" record (Phase 26.49, Rule 5).
 *
 * A duplicate is a DISTINCT identity: a fresh RID + resourceId, its own created
 * timestamp and a reset version, and freshly stamped author. It shares the
 * ORIGINAL's immutable payload and fingerprint byte-for-byte — duplicating never
 * modifies the source and never alters the copied payload. The preview is the
 * rid-keyed Runtime Preview pointer for the NEW rid (the same URL the capture
 * pipeline records for any new resource — not a regenerated screenshot).
 */
final class DuplicateManager
{
    private const SOURCE = 'my-designs';

    private const INITIAL_VERSION = '1.0.0';

    public function __construct(
        private readonly DesignPreviewGenerator $preview,
    ) {
    }

    /**
     * @param  array<string, mixed>  $record  the source record
     * @return array<string, mixed>  the new, persistable duplicate record
     */
    public function duplicate(array $record, ?string $author): array
    {
        $kind = (string) ($record['kind'] ?? '');
        $resourceId = 'd-'.Str::lower((string) Str::ulid());
        $rid = self::SOURCE.':'.$kind.':'.$resourceId;
        $now = Carbon::now()->toIso8601String();

        return [
            'rid' => $rid,
            'source' => self::SOURCE,
            'kind' => $kind,
            'resourceId' => $resourceId,
            'name' => $this->copyName($record['name'] ?? null),
            'description' => is_string($record['description'] ?? null) ? $record['description'] : null,
            'category' => is_string($record['category'] ?? null) ? $record['category'] : 'general',
            'tags' => is_array($record['tags'] ?? null) ? array_values(array_filter($record['tags'], 'is_string')) : [],
            'version' => self::INITIAL_VERSION,
            'versionNotes' => null,
            'visibility' => is_string($record['visibility'] ?? null) ? $record['visibility'] : 'private',
            'author' => $author,
            'compatibility' => is_array($record['compatibility'] ?? null) ? $record['compatibility'] : ['schemaVersion' => '1'],
            // Fingerprint is copied verbatim — the duplicate is the same design (Rule 5).
            'fingerprint' => is_string($record['fingerprint'] ?? null) ? $record['fingerprint'] : '',
            'preview' => $this->preview->generate($rid),
            'distribution' => is_array($record['distribution'] ?? null) ? $record['distribution'] : ['entitlement' => 'free'],
            // Payload copied verbatim — never modified (Rules 5/9).
            'payload' => is_array($record['payload'] ?? null) ? $record['payload'] : [],
            'archived' => false,
            'archivedAt' => null,
            'deleted' => false,
            'deletedAt' => null,
            'createdAt' => $now,
            'updatedAt' => $now,
        ];
    }

    private function copyName(mixed $name): string
    {
        $base = is_string($name) && trim($name) !== '' ? trim($name) : 'Untitled design';

        return $base.' (Copy)';
    }
}
