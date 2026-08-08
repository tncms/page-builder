<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Patterns\Migration;

/**
 * Runs the pattern schema-upgrade pipeline (Phase 25.2, Part 2). Migrations are
 * registered keyed by the version they upgrade FROM; {@see migrateManifest()} walks
 * the chain from a pattern's declared schema up to {@see CURRENT_SCHEMA}, stamping
 * the new version at each step. Everything operates on in-memory arrays — source
 * files are never touched. A pattern already at the current schema passes through
 * unchanged (backward compatible).
 */
final class PatternMigrator
{
    /** The schema version patterns are migrated up to (matches PatternManifest defaults). */
    public const CURRENT_SCHEMA = 2;

    /** @var array<int, PatternMigration> Keyed by fromVersion. */
    private array $migrations = [];

    public function register(PatternMigration $migration): self
    {
        $this->migrations[$migration->fromVersion()] = $migration;

        return $this;
    }

    public function needsMigration(int $schema): bool
    {
        return $schema < self::CURRENT_SCHEMA;
    }

    /**
     * Alias for {@see migrateManifest()} — the pattern-schema half of the two-part
     * migration API (Phase 25.2.x, Part 3), paired with
     * {@see BuilderMigrator::migrateBuilderSchema()}.
     *
     * @param  array<string, mixed>  $manifest
     * @return array<string, mixed>
     */
    public function migratePatternSchema(array $manifest): array
    {
        return $this->migrateManifest($manifest);
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return array<string, mixed>
     */
    public function migrateManifest(array $manifest): array
    {
        $schema = (int) ($manifest['schema'] ?? 1);
        while ($schema < self::CURRENT_SCHEMA && isset($this->migrations[$schema])) {
            $manifest = $this->migrations[$schema]->migrateManifest($manifest);
            $schema++;
            $manifest['schema'] = $schema;
        }

        return $manifest;
    }

    /**
     * Apply payload migrations from a pattern's ORIGINAL schema up to current. The
     * original schema is captured before the manifest is migrated (loader keeps it).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function migratePayload(array $payload, int $fromSchema): array
    {
        $schema = $fromSchema;
        while ($schema < self::CURRENT_SCHEMA && isset($this->migrations[$schema])) {
            $payload = $this->migrations[$schema]->migratePayload($payload);
            $schema++;
        }

        return $payload;
    }

    /**
     * Migrate a full manifest + payload bundle (used on import).
     *
     * @param  array<string, mixed>  $manifest
     * @param  array<string, mixed>  $payload
     * @return array{manifest: array<string, mixed>, payload: array<string, mixed>}
     */
    public function migrate(array $manifest, array $payload): array
    {
        $from = (int) ($manifest['schema'] ?? 1);

        return [
            'manifest' => $this->migrateManifest($manifest),
            'payload' => $this->migratePayload($payload, $from),
        ];
    }
}
