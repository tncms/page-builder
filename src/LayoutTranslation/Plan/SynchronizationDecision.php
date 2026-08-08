<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Plan;

/**
 * The default decision for a plan item (LT-6, per LT-0-ADR-001 D9).
 *
 * Layer: Translation (plan). Fail-closed: anything ambiguous or conflicting defaults
 * to `KeepTarget`. These are DEFAULT decisions only — actual selection and execution
 * are LT-7; LT-6 never mutates the target.
 */
enum SynchronizationDecision: string
{
    /** Apply the source change to the target. */
    case AcceptSource = 'accept_source';

    /** Keep the target as-is (the fail-closed default for review/conflict). */
    case KeepTarget = 'keep_target';

    /** Ignore this change. */
    case Ignore = 'ignore';
}
