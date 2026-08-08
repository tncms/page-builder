<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Patterns\Migration;

/**
 * Runs the builder-schema upgrade pipeline (Phase 25.2.x, Part 3): the second
 * migration dimension alongside {@see PatternMigrator}. Migrations are keyed by the
 * builder version they upgrade FROM and chained via their `toBuilder()`. No builder
 * migrations ship today, so {@see migrateBuilderSchema()} is identity — this is a
 * forward-compatibility seam so patterns can carry a `builder` version now and be
 * upgraded automatically when a future builder restructure lands.
 */
final class BuilderMigrator
{
    /** The builder version payloads are migrated up to. */
    public const CURRENT_BUILDER = '1.0';

    /** @var array<string, BuilderMigration> Keyed by fromBuilder. */
    private array $migrations = [];

    public function register(BuilderMigration $migration): self
    {
        $this->migrations[$migration->fromBuilder()] = $migration;

        return $this;
    }

    public function needsMigration(string $builder): bool
    {
        $builder = $builder !== '' ? $builder : self::CURRENT_BUILDER;

        return $builder !== self::CURRENT_BUILDER && isset($this->migrations[$builder]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function migrateBuilderSchema(array $payload, string $fromBuilder): array
    {
        $builder = $fromBuilder !== '' ? $fromBuilder : self::CURRENT_BUILDER;

        // Guard against cycles / missing links: stop as soon as there is no migration.
        while ($builder !== self::CURRENT_BUILDER && isset($this->migrations[$builder])) {
            $migration = $this->migrations[$builder];
            $payload = $migration->migratePayload($payload);
            $next = $migration->toBuilder();
            if ($next === $builder) {
                break;
            }
            $builder = $next;
        }

        return $payload;
    }
}
