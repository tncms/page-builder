<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Management;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Events\Dispatcher;
use Plugins\PageBuilder\Builder\Registry\BuilderResourceRegistry;
use Plugins\PageBuilder\Capture\UserDesignStore;
use Plugins\PageBuilder\Management\Events\DesignArchived;
use Plugins\PageBuilder\Management\Events\DesignDeleted;
use Plugins\PageBuilder\Management\Events\DesignDuplicated;
use Plugins\PageBuilder\Management\Events\DesignMetadataUpdated;
use Plugins\PageBuilder\Management\Events\DesignRestored;
use Plugins\PageBuilder\Management\Exceptions\DesignResourceNotFoundException;
use Plugins\PageBuilder\Management\Exceptions\UnauthorizedDesignActionException;
use Plugins\PageBuilder\Repository\Contracts\DesignResourceRepositoryInterface;

/**
 * The management lifecycle for user-created Design Resources — "My Designs"
 * (Phase 26.49).
 *
 * This is the single entry point for the management surface. It manages
 * resources; it never edits Builder content (Core Principle). It reads records
 * directly from the {@see UserDesignStore} — the authoritative store for the
 * my-designs source — so it can surface archived designs (which the repository,
 * and therefore normal browsing and the palette, deliberately hide). Every
 * mutation is metadata-only or a lifecycle flag: the normalized payload, the
 * fingerprint and the RID are immutable after capture (Rules 9/10/19). New
 * content only ever enters through the Design Capture Pipeline — never through
 * this manager.
 *
 * Refresh policy (Rule 12): resource-set changes (archive / restore / delete /
 * duplicate) refresh both the repository and the Builder Resource Registry;
 * metadata edits refresh only the repository and deliberately do NOT force a
 * registry rebuild.
 */
final class DesignResourceManager
{
    public function __construct(
        private readonly UserDesignStore $store,
        private readonly DesignResourceRepositoryInterface $repository,
        private readonly BuilderResourceRegistry $registry,
        private readonly OwnershipResolver $ownership,
        private readonly MetadataUpdater $metadata,
        private readonly ArchiveManager $archiver,
        private readonly DuplicateManager $duplicator,
        private readonly Dispatcher $events,
    ) {
    }

    // ---- Read ---------------------------------------------------------------

    /**
     * Build the My Designs management view-model: filtered, sorted display rows
     * plus the facets and normalized filter state.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function list(array $filters, ?Authenticatable $user): array
    {
        $filters = $this->normalizeFilters($filters);

        $records = array_values(array_filter(
            $this->store->all(),
            static fn (array $r): bool => ! ArchiveManager::isDeleted($r),
        ));

        $rows = [];
        foreach ($records as $record) {
            if (! $this->matches($record, $filters)) {
                continue;
            }
            $rows[] = $this->row($record, $user);
        }

        $rows = $this->sort($rows, $filters['sort']);

        return [
            'designs' => $rows,
            'facets' => $this->facets($records),
            'filters' => $filters,
            'count' => count($rows),
            'total' => count(array_filter($records, static fn (array $r): bool => ! ArchiveManager::isArchived($r))),
            'archivedTotal' => count(array_filter($records, static fn (array $r): bool => ArchiveManager::isArchived($r))),
        ];
    }

    /**
     * A single manageable record (never a deleted one), or null.
     *
     * @return array<string, mixed>|null
     */
    public function get(string $rid): ?array
    {
        $record = $this->store->findByRid($rid);
        if ($record === null || ArchiveManager::isDeleted($record)) {
            return null;
        }

        return $record;
    }

    // ---- Mutations ----------------------------------------------------------

    /**
     * Edit metadata only. Payload / fingerprint / RID are untouched (Rules 3/9/10).
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>  the updated record
     */
    public function updateMetadata(string $rid, array $input, ?Authenticatable $user): array
    {
        $record = $this->authorize($rid, $user);

        $updated = $this->metadata->apply($record, $input);
        $changed = $this->metadata->changedFields($record, $updated);

        $this->store->save((string) $record['resourceId'], $updated);
        // Metadata edits refresh the repository but never force a registry rebuild (Rule 12).
        $this->repository->refresh();

        $this->events->dispatch(new DesignMetadataUpdated($rid, $changed, $this->ownership->currentAuthor($user)));

        return $updated;
    }

    public function archive(string $rid, ?Authenticatable $user): void
    {
        $record = $this->authorize($rid, $user);

        $this->store->save((string) $record['resourceId'], $this->archiver->archive($record));
        $this->refreshResourceSet();

        $this->events->dispatch(new DesignArchived($rid, $this->ownership->currentAuthor($user)));
    }

    public function restore(string $rid, ?Authenticatable $user): void
    {
        $record = $this->authorize($rid, $user);

        $this->store->save((string) $record['resourceId'], $this->archiver->restore($record));
        $this->refreshResourceSet();

        $this->events->dispatch(new DesignRestored($rid, $this->ownership->currentAuthor($user)));
    }

    /**
     * Soft-delete only — the record is flagged, never removed (Rule 8).
     */
    public function delete(string $rid, ?Authenticatable $user): void
    {
        $record = $this->authorize($rid, $user);

        $this->store->save((string) $record['resourceId'], $this->archiver->softDelete($record));
        $this->refreshResourceSet();

        $this->events->dispatch(new DesignDeleted($rid, $this->ownership->currentAuthor($user)));
    }

    /**
     * Duplicate into a new identity that shares the immutable payload + fingerprint.
     *
     * @return string  the new RID
     */
    public function duplicate(string $rid, ?Authenticatable $user): string
    {
        $record = $this->authorize($rid, $user);

        $copy = $this->duplicator->duplicate($record, $this->ownership->currentAuthor($user));
        $this->store->save((string) $copy['resourceId'], $copy);
        $this->refreshResourceSet();

        $newRid = (string) $copy['rid'];
        $this->events->dispatch(new DesignDuplicated($rid, $newRid, $this->ownership->currentAuthor($user)));

        return $newRid;
    }

    /**
     * Open a design in the Builder as an ISOLATED working copy (Rules 4/22/23).
     *
     * Returns a deep, id-free copy of the saved payload for the Builder to load
     * as a fresh draft. The repository payload is only read — never mutated — so
     * the stored resource stays byte-identical no matter what the Builder does.
     * Finishing the edit flows through "Save as Design" (a new Capture), never an
     * update of this resource.
     *
     * @return array<string, mixed>
     */
    public function openInBuilder(string $rid, ?Authenticatable $user): array
    {
        $record = $this->authorize($rid, $user);

        $payload = is_array($record['payload'] ?? null) ? $record['payload'] : [];
        // Deep copy so the Builder working copy is fully detached from the store.
        $workingCopy = json_decode((string) json_encode($payload), true);

        return [
            'rid' => $rid,
            'kind' => (string) $record['kind'],
            'name' => (string) ($record['name'] ?? ''),
            'payload' => is_array($workingCopy) ? $workingCopy : [],
            'working' => true,
        ];
    }

    // ---- Internals ----------------------------------------------------------

    /**
     * Load a manageable record and verify the actor may manage it, else throw.
     *
     * @return array<string, mixed>
     *
     * @throws DesignResourceNotFoundException
     * @throws UnauthorizedDesignActionException
     */
    private function authorize(string $rid, ?Authenticatable $user): array
    {
        $record = $this->get($rid);
        if ($record === null) {
            throw DesignResourceNotFoundException::forRid($rid);
        }
        if (! $this->ownership->canManage($record, $user)) {
            throw UnauthorizedDesignActionException::forRid($rid);
        }

        return $record;
    }

    private function refreshResourceSet(): void
    {
        $this->repository->refresh();
        $this->registry->refresh();
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<string, mixed>  $filters
     */
    private function matches(array $record, array $filters): bool
    {
        if ($filters['archived'] === true) {
            if (! ArchiveManager::isArchived($record)) {
                return false;
            }
        } elseif (ArchiveManager::isArchived($record)) {
            return false;
        }

        if ($filters['kind'] !== null && ($record['kind'] ?? null) !== $filters['kind']) {
            return false;
        }
        if ($filters['category'] !== null && ($record['category'] ?? null) !== $filters['category']) {
            return false;
        }
        if ($filters['visibility'] !== null && $this->visibility($record) !== $filters['visibility']) {
            return false;
        }
        if ($filters['version'] !== null && ($record['version'] ?? null) !== $filters['version']) {
            return false;
        }
        if ($filters['tag'] !== null && ! in_array($filters['tag'], $this->tags($record), true)) {
            return false;
        }
        if ($filters['search'] !== null && ! $this->matchesSearch($record, $filters['search'])) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function matchesSearch(array $record, string $needle): bool
    {
        $needle = mb_strtolower($needle);
        $haystacks = [
            (string) ($record['name'] ?? ''),
            (string) ($record['description'] ?? ''),
            (string) ($record['rid'] ?? ''),
            implode(' ', $this->tags($record)),
        ];
        foreach ($haystacks as $hay) {
            if ($hay !== '' && mb_stripos($hay, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private function row(array $record, ?Authenticatable $user): array
    {
        return [
            'rid' => (string) ($record['rid'] ?? ''),
            'name' => (string) ($record['name'] ?? ''),
            'kind' => (string) ($record['kind'] ?? ''),
            'category' => (string) ($record['category'] ?? 'general'),
            'tags' => $this->tags($record),
            'version' => (string) ($record['version'] ?? '1.0.0'),
            'versionNotes' => is_string($record['versionNotes'] ?? null) ? $record['versionNotes'] : null,
            'visibility' => $this->visibility($record),
            'description' => is_string($record['description'] ?? null) ? $record['description'] : null,
            'author' => is_string($record['author'] ?? null) ? $record['author'] : null,
            'archived' => ArchiveManager::isArchived($record),
            'createdAt' => (string) ($record['createdAt'] ?? ''),
            'updatedAt' => (string) ($record['updatedAt'] ?? ''),
            // Preview is the rid-keyed Runtime Preview route recorded at capture (Rule 11).
            'previewUrl' => is_string($record['preview']['previewUrl'] ?? null) ? $record['preview']['previewUrl'] : '',
            'canManage' => $this->ownership->canManage($record, $user),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function sort(array $rows, string $sort): array
    {
        $comparators = [
            'newest' => static fn (array $a, array $b): int => strcmp((string) $b['createdAt'], (string) $a['createdAt']),
            'oldest' => static fn (array $a, array $b): int => strcmp((string) $a['createdAt'], (string) $b['createdAt']),
            'updated' => static fn (array $a, array $b): int => strcmp((string) $b['updatedAt'], (string) $a['updatedAt']),
            'alphabetical' => static fn (array $a, array $b): int => strcasecmp((string) $a['name'], (string) $b['name']),
        ];
        usort($rows, $comparators[$sort] ?? $comparators['newest']);

        return $rows;
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     * @return array<string, array<int, string>>
     */
    private function facets(array $records): array
    {
        $categories = [];
        $tags = [];
        $versions = [];
        foreach ($records as $record) {
            $categories[(string) ($record['category'] ?? 'general')] = true;
            $versions[(string) ($record['version'] ?? '1.0.0')] = true;
            foreach ($this->tags($record) as $tag) {
                $tags[$tag] = true;
            }
        }

        return [
            'categories' => $this->sortedKeys($categories),
            'tags' => $this->sortedKeys($tags),
            'versions' => $this->sortedKeys($versions),
            'visibilities' => MetadataUpdater::VISIBILITIES,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function normalizeFilters(array $filters): array
    {
        $sort = is_string($filters['sort'] ?? null) ? strtolower(trim($filters['sort'])) : '';

        return [
            'kind' => $this->clean($filters['kind'] ?? null),
            'category' => $this->clean($filters['category'] ?? null),
            'visibility' => $this->clean($filters['visibility'] ?? null),
            'version' => $this->clean($filters['version'] ?? null),
            'tag' => $this->clean($filters['tag'] ?? null),
            'search' => $this->clean($filters['search'] ?? null),
            'archived' => ! empty($filters['archived']),
            'sort' => in_array($sort, ['newest', 'oldest', 'updated', 'alphabetical'], true) ? $sort : 'newest',
        ];
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function visibility(array $record): string
    {
        $value = is_string($record['visibility'] ?? null) ? $record['visibility'] : 'private';

        return in_array($value, MetadataUpdater::VISIBILITIES, true) ? $value : 'private';
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<int, string>
     */
    private function tags(array $record): array
    {
        return is_array($record['tags'] ?? null) ? array_values(array_filter($record['tags'], 'is_string')) : [];
    }

    private function clean(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<string, bool>  $set
     * @return array<int, string>
     */
    private function sortedKeys(array $set): array
    {
        $keys = array_keys($set);
        sort($keys);

        return $keys;
    }
}
