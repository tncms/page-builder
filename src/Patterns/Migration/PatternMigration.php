<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Patterns\Migration;

/**
 * One step in the pattern schema-upgrade pipeline (Phase 25.2, Part 2). A migration
 * upgrades a pattern's manifest (and, if needed, payload) from `fromVersion()` to
 * `fromVersion() + 1`. Migrations run IN MEMORY at load / import — they never
 * rewrite the source manifest.json / payload.json on disk. They must be additive so
 * older patterns keep working.
 */
interface PatternMigration
{
    /** The schema version this migration upgrades FROM (to fromVersion + 1). */
    public function fromVersion(): int;

    /**
     * @param  array<string, mixed>  $manifest
     * @return array<string, mixed>
     */
    public function migrateManifest(array $manifest): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function migratePayload(array $payload): array;
}
