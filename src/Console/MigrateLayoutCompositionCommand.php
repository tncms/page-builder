<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Console;

use App\Models\User;
use Illuminate\Console\Command;
use Plugins\PageBuilder\Authority\AuthorityResponse;
use Plugins\PageBuilder\Authority\ContentAuthority;
use Plugins\PageBuilder\Authority\PageBuilderAuthorityException;
use Plugins\PageBuilder\Support\LayoutCompositionMigrator;
use Plugins\PageBuilder\Support\LayoutStore;
use TheNguyen\CMS\ContentAuthority\ContentWriteException;

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
 *
 * PB-CORE37-R1 — running in the console is not a permission:
 * - the owner's Core content must not be extension-managed (draft repair and publish);
 * - `--publish` changes public output, so it needs `--actor=<user id>` holding the full
 *   publication authority ({@see ContentAuthority::assertCanPublish()}) and runs as a
 *   Core content unit (Core row locked + revision checked, then the layout row).
 */
class MigrateLayoutCompositionCommand extends Command
{
    protected $signature = 'page-builder:migrate-composition {ownerType} {ownerId}
        {--publish : Also publish the repaired draft (requires --actor)}
        {--actor= : User id the publication is performed as (authorized like the editor)}';

    protected $description = 'Repair a page/post layout draft into the Section → Container → Column composition tree.';

    public function handle(LayoutStore $store, LayoutCompositionMigrator $migrator, ContentAuthority $authority): int
    {
        $ownerType = (string) $this->argument('ownerType');
        $ownerId = (string) $this->argument('ownerId');

        $content = ContentAuthority::canonicalFor($ownerType, $ownerId);
        if ($content === null) {
            $this->error("No page/post content found for {$ownerType}#{$ownerId}.");

            return self::FAILURE;
        }

        $actor = null;
        try {
            $authority->assertNotManaged($content);

            if ($this->option('publish')) {
                $actor = $this->actor();
                $authority->assertCanPublish($content, $actor);
            }
        } catch (PageBuilderAuthorityException $e) {
            $this->error(AuthorityResponse::message($e));

            return self::FAILURE;
        }

        // Captured before any write: the publication unit is checked against it.
        $revision = $authority->revisionOf($content);

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

        if ($actor !== null) {
            try {
                $authority->withContentUnit($content, $revision, $actor, fn () => $store->publish($ownerType, $ownerId, (string) $actor->getKey()), recheck: ContentAuthority::PUBLISH);
            } catch (PageBuilderAuthorityException|ContentWriteException $e) {
                $this->error('Not published: '.AuthorityResponse::message($e));

                return self::FAILURE;
            }

            $this->info('Published the repaired draft.');
        }

        return self::SUCCESS;
    }

    /** @throws PageBuilderAuthorityException */
    private function actor(): User
    {
        $id = $this->option('actor');
        $user = is_string($id) && ctype_digit($id) ? User::query()->find((int) $id) : null;

        return $user ?? throw PageBuilderAuthorityException::forbidden(
            'actor_required',
            'Publishing from the console requires --actor=<user id> of a user allowed to publish this content.',
        );
    }
}
