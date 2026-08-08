<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Management;

use Illuminate\Support\Carbon;

/**
 * Pure state transitions for the archive / restore / soft-delete lifecycle of a
 * "My Designs" record (Phase 26.49, Rules 6/7/8).
 *
 * Archive hides a resource from normal browsing while keeping it recoverable;
 * restore is its exact inverse; delete is soft ONLY — it flags the record and
 * never removes it (a future cleanup job may purge archived/deleted records, not
 * implemented here). Every method returns a NEW record and never touches payload,
 * fingerprint, RID or any other immutable field (Rule 19).
 */
final class ArchiveManager
{
    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    public function archive(array $record): array
    {
        $record['archived'] = true;
        $record['archivedAt'] = Carbon::now()->toIso8601String();
        $record['updatedAt'] = $record['archivedAt'];

        return $record;
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    public function restore(array $record): array
    {
        $record['archived'] = false;
        $record['archivedAt'] = null;
        $record['updatedAt'] = Carbon::now()->toIso8601String();

        return $record;
    }

    /**
     * Soft-delete: flag only, never remove (Rule 8).
     *
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    public function softDelete(array $record): array
    {
        $record['deleted'] = true;
        $record['deletedAt'] = Carbon::now()->toIso8601String();
        $record['updatedAt'] = $record['deletedAt'];

        return $record;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    public static function isArchived(array $record): bool
    {
        return (bool) ($record['archived'] ?? false);
    }

    /**
     * @param  array<string, mixed>  $record
     */
    public static function isDeleted(array $record): bool
    {
        return (bool) ($record['deleted'] ?? false);
    }
}
