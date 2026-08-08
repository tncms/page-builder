<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Console;

use Illuminate\Console\Command;
use Plugins\PageBuilder\Support\LayoutCompositionMigrator;
use Plugins\PageBuilder\Support\LayoutStore;

/**
 * Explicit, opt-in repair of a legacy/orphaned layout draft into the composition
 * tree (Phase 25.3.2 §10).
 *
 * Fresh preset insertion always produces Section → Container → Column, but drafts
 * authored before the composition engine (or legacy "columns" sections) can hold
 * columns directly under a section (Section → Columns, no Container), which the
 * Navigator then shows without a Container and the builder cannot lay out. Per the
 * spec this is NEVER auto-migrated; this command is the deliberate opt-in that
 * repairs one owner's draft (and optionally publishes it).
 */
class MigrateLayoutCompositionCommand extends Command
{
    protected $signature = 'page-builder:migrate-composition {ownerType} {ownerId} {--publish : Also publish the repaired draft}';

    protected $description = 'Repair a page/post layout draft into the Section → Container → Column composition tree.';

    public function handle(LayoutStore $store, LayoutCompositionMigrator $migrator): int
    {
        $ownerType = (string) $this->argument('ownerType');
        $ownerId = (string) $this->argument('ownerId');

        $draft = $store->getDraftPayload($ownerType, $ownerId);
        if ($draft === null) {
            $this->error("No draft layout found for {$ownerType}#{$ownerId}.");

            return self::FAILURE;
        }

        $migrated = $migrator->migratePayload($draft);
        if ($migrated === $draft) {
            $this->info("Draft for {$ownerType}#{$ownerId} is already composition-based — nothing to repair.");

            return self::SUCCESS;
        }

        $store->saveDraft($ownerType, $ownerId, $migrated);
        $this->info("Repaired the draft for {$ownerType}#{$ownerId} into the composition tree.");

        if ($this->option('publish')) {
            $store->publish($ownerType, $ownerId);
            $this->info('Published the repaired draft.');
        }

        return self::SUCCESS;
    }
}
