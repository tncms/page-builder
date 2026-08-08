<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Community\Synchronization;

use Plugins\PageBuilder\Community\CommunityResourceStore;
use Plugins\PageBuilder\Community\Manifest\ManifestParser;
use Plugins\PageBuilder\Community\Publishing\ResourceStatus;

/**
 * The immutable **Repository Index** (Phase 26.50C, Rule 2).
 *
 * A Repository Index is the metadata-only projection a repository publishes for
 * synchronization: a list of {@see RepositoryIndexEntry} for its exposed resources
 * (published + deprecated) plus a list of tombstones for archived/removed ones. It
 * carries NO payload — synchronization is transport of metadata, never content
 * (Rules 2, 16).
 *
 * An index is either FULL (a complete snapshot of the repository's exposed state) or
 * INCREMENTAL (a delta of changed entries plus explicit tombstones) so a client can
 * synchronise cheaply (Rule 6). The same immutable structure is what a filesystem
 * mirror, an enterprise mirror, a portable package or an HTTP endpoint transports —
 * the protocol makes no transport assumptions (Rule 12).
 *
 * @see docs/repository-index.md
 * @see docs/synchronization-protocol.md
 */
final class RepositoryIndex
{
    public const SCHEMA = '1.0';

    public const MODE_FULL = 'full';

    public const MODE_INCREMENTAL = 'incremental';

    /**
     * @param  array<int, RepositoryIndexEntry>  $entries  active (published / deprecated)
     * @param  array<int, RepositoryIndexEntry>  $removed  tombstones (archived / removed)
     */
    public function __construct(
        public readonly string $repository,
        public readonly string $mode,
        public readonly int $generatedAt,
        public readonly array $entries,
        public readonly array $removed = [],
        public readonly string $schemaVersion = self::SCHEMA,
    ) {}

    /**
     * @param  array<int, RepositoryIndexEntry>  $entries
     * @param  array<int, RepositoryIndexEntry>  $removed
     */
    public static function full(string $repository, array $entries, ?int $generatedAt = null, array $removed = []): self
    {
        return new self($repository, self::MODE_FULL, $generatedAt ?? time(), array_values($entries), array_values($removed));
    }

    /**
     * @param  array<int, RepositoryIndexEntry>  $entries
     * @param  array<int, RepositoryIndexEntry>  $removed
     */
    public static function incremental(string $repository, array $entries, array $removed = [], ?int $generatedAt = null): self
    {
        return new self($repository, self::MODE_INCREMENTAL, $generatedAt ?? time(), array_values($entries), array_values($removed));
    }

    /**
     * Build the FULL index a repository publishes, directly from its local store
     * (Repository → Repository Index). Exposed resources (published + deprecated)
     * become active entries; archived resources become tombstones so clients can
     * detect archival even from a full snapshot (Rule 4). Unpublished work
     * (draft / validated / packaged) never enters an index (Rule 13). No payload is
     * read.
     */
    public static function fromStore(CommunityResourceStore $store, string $repository, ?int $generatedAt = null, ManifestParser $parser = new ManifestParser): self
    {
        $entries = [];
        $removed = [];

        foreach ($store->all() as $record) {
            $manifest = $parser->fromArray($record['manifest']);
            $status = ResourceStatus::tryFromString($record['lifecycle']['status'] ?? null) ?? ResourceStatus::Published;
            $package = is_array($record['lifecycle']['package'] ?? null) ? $record['lifecycle']['package'] : null;

            if ($status->isExposed()) {
                $entries[] = RepositoryIndexEntry::fromManifest($manifest, $status, $package);
            } elseif ($status->isArchived()) {
                $removed[] = RepositoryIndexEntry::fromManifest($manifest, ResourceStatus::Archived, $package);
            }
            // Draft / Validated / Packaged remain purely local — not indexed.
        }

        return self::full($repository, $entries, $generatedAt, $removed);
    }

    public function isFull(): bool
    {
        return $this->mode === self::MODE_FULL;
    }

    public function isIncremental(): bool
    {
        return $this->mode === self::MODE_INCREMENTAL;
    }

    public function isEmpty(): bool
    {
        return $this->entries === [] && $this->removed === [];
    }

    public function count(): int
    {
        return count($this->entries);
    }

    /**
     * @return array<string, RepositoryIndexEntry>
     */
    public function entriesByRid(): array
    {
        $map = [];
        foreach ($this->entries as $entry) {
            $map[$entry->rid] = $entry;
        }

        return $map;
    }

    /**
     * @return array<string, RepositoryIndexEntry>
     */
    public function removedByRid(): array
    {
        $map = [];
        foreach ($this->removed as $entry) {
            $map[$entry->rid] = $entry;
        }

        return $map;
    }

    /**
     * @return array<int, string>
     */
    public function rids(): array
    {
        return array_map(static fn (RepositoryIndexEntry $e): string => $e->rid, $this->entries);
    }

    /**
     * A deterministic digest of the index's identity-bearing content, so callers can
     * cheaply tell whether two indexes describe the same state (never uses payload).
     */
    public function fingerprint(): string
    {
        $parts = [];
        foreach ([...$this->entries, ...$this->removed] as $entry) {
            $parts[] = $entry->rid.'@'.$entry->version.'#'.$entry->fingerprint.':'.$entry->status;
        }
        sort($parts);

        return hash('sha256', implode('|', $parts));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $entries = [];
        foreach ($data['entries'] ?? [] as $entry) {
            if (is_array($entry)) {
                $entries[] = RepositoryIndexEntry::fromArray($entry);
            }
        }

        $removed = [];
        foreach ($data['removed'] ?? [] as $entry) {
            if (is_array($entry)) {
                $removed[] = RepositoryIndexEntry::fromArray($entry);
            }
        }

        return new self(
            repository: (string) ($data['repository'] ?? ''),
            mode: (string) ($data['mode'] ?? self::MODE_FULL),
            generatedAt: (int) ($data['generated_at'] ?? 0),
            entries: $entries,
            removed: $removed,
            schemaVersion: (string) ($data['schema_version'] ?? self::SCHEMA),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'schema_version' => $this->schemaVersion,
            'repository' => $this->repository,
            'mode' => $this->mode,
            'generated_at' => $this->generatedAt,
            'entries' => array_map(static fn (RepositoryIndexEntry $e): array => $e->toArray(), $this->entries),
            'removed' => array_map(static fn (RepositoryIndexEntry $e): array => $e->toArray(), $this->removed),
        ];
    }
}
