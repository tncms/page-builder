<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Persistence\Console;

use Illuminate\Console\Command;
use Plugins\PageBuilder\LayoutTranslation\Persistence\LayoutTranslationBindingReconciler;

/**
 * Explicit, additive backfill of default-locale translation bindings — LT-2.
 *
 * Layer: Persistence (application-level reconciliation). It is never run
 * automatically and never executes during a migration: an operator runs it once
 * the schema exists to bind existing layouts. It is fail-closed and idempotent and
 * reports records it could not canonically resolve rather than fabricating
 * identity.
 */
final class BackfillLayoutTranslationBindingsCommand extends Command
{
    protected $signature = 'page-builder:layout-translation:backfill';

    protected $description = 'Backfill default-locale Page Builder layout translation bindings (additive, fail-closed, idempotent).';

    public function handle(LayoutTranslationBindingReconciler $reconciler): int
    {
        $report = $reconciler->reconcile();

        $this->info('Layout translation binding backfill complete.');
        $this->table(
            ['scanned', 'created', 'skipped', 'unresolved', 'conflicts'],
            [[$report->scanned, $report->created, $report->skipped, $report->unresolved, $report->conflicts]],
        );

        foreach ($report->unresolvedRecords as $record) {
            $this->warn(sprintf(
                'Unresolved: %s#%s (%s) — no binding created.',
                $record['owner_type'] ?? '?',
                (string) ($record['owner_id'] ?? '?'),
                $record['reason'] ?? 'unknown',
            ));
        }

        return self::SUCCESS;
    }
}
