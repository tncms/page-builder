<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Patterns\Migration;

/**
 * One step in the BUILDER-schema upgrade pipeline (Phase 25.2.x, Part 3). Distinct
 * from {@see PatternMigration} (which upgrades the pattern manifest schema): a
 * builder migration upgrades a pattern's PAYLOAD structure across Builder versions
 * (e.g. a future Section → Container → Column → Elements restructure). It is an
 * architecture seam — no builder migrations exist yet, so today the pipeline is
 * identity. Migrations must be deterministic and non-destructive.
 */
interface BuilderMigration
{
    /** The builder version this migration upgrades FROM (e.g. "1.0"). */
    public function fromBuilder(): string;

    /** The builder version this migration upgrades TO (e.g. "2.0"). */
    public function toBuilder(): string;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function migratePayload(array $payload): array;
}
