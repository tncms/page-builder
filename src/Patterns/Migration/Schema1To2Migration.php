<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Patterns\Migration;

/**
 * Upgrades a schema-1 pattern (pre Phase 25.1) to schema 2 (Phase 25.2, Part 2).
 * Schema 2 introduced the `builder` version and the `preview` strategy block. This
 * migration is purely ADDITIVE — it fills those in with the same defaults
 * {@see \Plugins\PageBuilder\Patterns\PatternManifest} already applies, so a
 * migrated schema-1 pattern behaves identically to before. The payload is unchanged.
 */
final class Schema1To2Migration implements PatternMigration
{
    public function fromVersion(): int
    {
        return 1;
    }

    public function migrateManifest(array $manifest): array
    {
        if (! isset($manifest['builder'])) {
            $manifest['builder'] = '1.0';
        }

        if (! isset($manifest['preview']) || ! is_array($manifest['preview'])) {
            $thumbnail = trim((string) ($manifest['thumbnail'] ?? '')) ?: 'preview.webp';
            $manifest['preview'] = ['type' => 'image', 'src' => $thumbnail];
        }

        return $manifest;
    }

    public function migratePayload(array $payload): array
    {
        // No payload shape change between schema 1 and 2.
        return $payload;
    }
}
