<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Execution\Commands;

use Plugins\PageBuilder\LayoutTranslation\Execution\Acceptance\SynchronizationAcceptanceSet;
use Plugins\PageBuilder\LayoutTranslation\Execution\Provenance\PlanProvenance;
use Plugins\PageBuilder\LayoutTranslation\Identity\PageTranslationKey;
use Plugins\PageBuilder\LayoutTranslation\Plan\SynchronizationPlan;

/**
 * The immutable input bundle for a synchronization execution (LT-7B3, per
 * LT-7-EXECUTION-ARCHITECTURE §1).
 *
 * Layer: Translation (execution command). It binds the target, the immutable LT-6 plan (verbatim), the
 * `PlanProvenance` the plan was built from, the reviewer's explicit decisions, and an optional actor for
 * audit. The coordinator reads canonical state from these identities — it never trusts a payload smuggled
 * in the command.
 */
final class ExecuteSynchronizationCommand
{
    public function __construct(
        public readonly PageTranslationKey $target,
        public readonly SynchronizationPlan $plan,
        public readonly PlanProvenance $provenance,
        public readonly SynchronizationAcceptanceSet $acceptance,
        public readonly ?string $actor = null,
    ) {}
}
