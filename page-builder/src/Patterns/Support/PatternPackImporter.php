<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Patterns\Support;

use InvalidArgumentException;
use Plugins\PageBuilder\Patterns\Migration\PatternMigrator;
use Plugins\PageBuilder\Patterns\Pattern;
use Plugins\PageBuilder\Patterns\Packs\PatternPack;

/**
 * Loads + validates a whole pattern pack from a bundle or directory (Phase 25.2.x,
 * Part 5), building on {@see PatternImporter} for the individual patterns. It
 * validates: the pack metadata (id required), that every pattern the pack declares
 * is actually present, and — via the migrator + the per-pattern importer — schema
 * compatibility. Fatal problems throw; the imported patterns are returned migrated
 * to the current schema. Dependency requirements are PARSED (they ride along on each
 * manifest) but never enforced here.
 */
final class PatternPackImporter
{
    public function __construct(
        private readonly PatternImporter $patterns,
        private readonly ?PatternMigrator $migrator = null,
    ) {}

    /**
     * @return array{pack: PatternPack, patterns: array<int, Pattern>}
     */
    public function fromDirectory(string $directory): array
    {
        $dir = rtrim($directory, '/\\');

        $packData = $this->readJson($dir.DIRECTORY_SEPARATOR.'pack.json');
        if ($packData === null) {
            throw new InvalidArgumentException("Missing or invalid pack.json in [{$directory}].");
        }

        $bundles = [];
        foreach ((array) glob($dir.DIRECTORY_SEPARATOR.'patterns'.DIRECTORY_SEPARATOR.'*.json') as $file) {
            $bundle = $this->readJson((string) $file);
            if ($bundle !== null) {
                $bundles[] = $bundle;
            }
        }

        return $this->fromArray(['pack' => $packData, 'patterns' => $bundles]);
    }

    /**
     * @param  array<string, mixed>  $bundle
     * @return array{pack: PatternPack, patterns: array<int, Pattern>}
     */
    public function fromArray(array $bundle): array
    {
        $packData = is_array($bundle['pack'] ?? null) ? $bundle['pack'] : [];
        if (trim((string) ($packData['id'] ?? '')) === '') {
            throw new InvalidArgumentException('Invalid pack: pack.id is required.');
        }

        $patterns = [];
        $importedIds = [];
        foreach ((array) ($bundle['patterns'] ?? []) as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $manifest = is_array($entry['manifest'] ?? null) ? $entry['manifest'] : [];
            $payload = is_array($entry['payload'] ?? null) ? $entry['payload'] : [];

            // Bring older-schema patterns up to date before per-pattern validation.
            if ($this->migrator !== null) {
                $migrated = $this->migrator->migrate($manifest, $payload);
                $manifest = $migrated['manifest'];
                $payload = $migrated['payload'];
            }

            $pattern = $this->patterns->fromArray($manifest, $payload);
            $patterns[] = $pattern;
            $importedIds[] = $pattern->id();
        }

        $pack = PatternPack::fromArray($packData);
        $this->assertDeclaredPatternsPresent($pack, $importedIds);

        return ['pack' => $pack, 'patterns' => $patterns];
    }

    /**
     * @param  array<int, string>  $importedIds
     */
    private function assertDeclaredPatternsPresent(PatternPack $pack, array $importedIds): void
    {
        $missing = array_values(array_diff($pack->patternIds, $importedIds));
        if ($missing !== []) {
            throw new InvalidArgumentException(
                'Invalid pack: declared patterns are missing from the bundle: '.implode(', ', $missing),
            );
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readJson(string $file): ?array
    {
        if (! is_file($file)) {
            return null;
        }
        $decoded = json_decode((string) file_get_contents($file), true);

        return is_array($decoded) ? $decoded : null;
    }
}
