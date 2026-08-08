<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Patterns;

/**
 * Filesystem discovery for a single source directory. Finds every
 * `<dir>/<slug>/manifest.json` or `<dir>/<category>/<slug>/manifest.json`, reads
 * the manifest, and builds a {@see Pattern} whose payload is loaded lazily from
 * the sibling `payload.json` only when first needed (§20).
 *
 * No framework dependencies — pure filesystem + json so it is trivially testable
 * against fixture directories.
 */
final class PatternLoader
{
    /**
     * @param  \Plugins\PageBuilder\Patterns\Migration\PatternMigrator|null  $migrator
     *   Optional pattern-schema migrator (Phase 25.2). When set, a pattern's manifest
     *   is migrated to the current schema at load and its payload is migrated lazily
     *   on first access. Null (e.g. in unit tests) means no migration — identity.
     * @param  \Plugins\PageBuilder\Patterns\Migration\BuilderMigrator|null  $builderMigrator
     *   Optional builder-schema migrator (Phase 25.2.x, Part 3). Applied to the
     *   payload after pattern-schema migration; identity until builder migrations ship.
     */
    public function __construct(
        private readonly ?\Plugins\PageBuilder\Patterns\Migration\PatternMigrator $migrator = null,
        private readonly ?\Plugins\PageBuilder\Patterns\Migration\BuilderMigrator $builderMigrator = null,
    ) {}

    /**
     * @return array<int, Pattern>
     */
    public function scan(PatternSource $source): array
    {
        $patterns = [];
        foreach ($this->entries($source) as $entry) {
            $pattern = $this->loadOne($entry['file'], $source);
            if ($pattern !== null) {
                $patterns[] = $pattern;
            }
        }

        return $patterns;
    }

    /**
     * Raw manifest entries (file path + decoded data) for a source, so callers can
     * validate the un-normalised manifest before building a Pattern.
     *
     * @return array<int, array{file: string, data: array<string, mixed>}>
     */
    public function entries(PatternSource $source): array
    {
        $dir = rtrim($source->path, '/\\');
        if ($dir === '' || ! is_dir($dir)) {
            return [];
        }

        $entries = [];
        foreach ($this->manifestFiles($dir) as $manifestFile) {
            $data = $this->readJson($manifestFile);
            if ($data !== null) {
                $entries[] = ['file' => $manifestFile, 'data' => $data];
            }
        }

        return $entries;
    }

    public function loadOne(string $manifestFile, PatternSource $source, string $packId = ''): ?Pattern
    {
        $data = $this->readJson($manifestFile);
        if ($data === null || trim((string) ($data['id'] ?? '')) === '') {
            return null;
        }

        $directory = dirname($manifestFile);

        // Migrate the manifest to the current schema in memory (never on disk); keep
        // the original schema + builder so the (lazy) payload can be migrated over the
        // same ranges (pattern schema first, then builder schema).
        $originalSchema = (int) ($data['schema'] ?? 1);
        $originalBuilder = (string) ($data['builder'] ?? '1.0');
        if ($this->migrator !== null) {
            $data = $this->migrator->migrateManifest($data);
        }

        $manifest = PatternManifest::fromArray($data, $source->id);
        $migrator = $this->migrator;
        $builderMigrator = $this->builderMigrator;

        return new Pattern(
            manifest: $manifest,
            payloadLoader: function () use ($directory, $migrator, $builderMigrator, $originalSchema, $originalBuilder): array {
                $payload = $this->loadPayload($directory);
                if ($migrator !== null) {
                    $payload = $migrator->migratePayload($payload, $originalSchema);
                }
                if ($builderMigrator !== null) {
                    $payload = $builderMigrator->migrateBuilderSchema($payload, $originalBuilder);
                }

                return $payload;
            },
            directory: $directory,
            sourceId: $source->id,
            priority: $source->priority,
            packId: $packId,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function loadPayload(string $directory): array
    {
        $file = $directory.DIRECTORY_SEPARATOR.'payload.json';
        $data = $this->readJson($file);

        return is_array($data) ? $data : [];
    }

    public function payloadFileExists(string $directory): bool
    {
        return is_file($directory.DIRECTORY_SEPARATOR.'payload.json');
    }

    /**
     * @return array<int, string>
     */
    private function manifestFiles(string $dir): array
    {
        $files = array_merge(
            (array) glob($dir.'/*/manifest.json'),
            (array) glob($dir.'/*/*/manifest.json'),
        );

        return array_values(array_filter($files, 'is_file'));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readJson(string $file): ?array
    {
        if (! is_file($file)) {
            return null;
        }

        $raw = file_get_contents($file);
        if ($raw === false) {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }
}
