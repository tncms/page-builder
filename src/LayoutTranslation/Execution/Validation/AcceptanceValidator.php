<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Execution\Validation;

use Plugins\PageBuilder\LayoutTranslation\Diff\NodeChangeType;
use Plugins\PageBuilder\LayoutTranslation\Execution\Acceptance\SynchronizationAcceptanceSet;
use Plugins\PageBuilder\LayoutTranslation\Plan\SynchronizationPlan;

/**
 * Validates the reviewer's decisions against the plan, before any transaction (LT-7B3, per
 * LT-7-EXECUTION-ARCHITECTURE §5).
 *
 * Layer: Translation (coordinator validation). Pure and read-only. It rejects a decision for an origin
 * that is not in the plan, and an accepted item whose change type cannot be lowered (reserved
 * Style/Setting, or the three-way-only `Conflicted`/`Unchanged`). Returns a stable reason discriminator,
 * or null when the acceptance set is valid.
 */
final class AcceptanceValidator
{
    public const UNKNOWN_ORIGIN = 'unknown_origin';

    public const UNSUPPORTED_CHANGE = 'unsupported_change';

    public function validate(SynchronizationPlan $plan, SynchronizationAcceptanceSet $acceptance): ?string
    {
        $changeByOrigin = [];
        foreach ($plan->items() as $item) {
            $changeByOrigin[$item->origin] = $item->sourceChange;
        }

        foreach ($acceptance->all() as $decision) {
            if (! isset($changeByOrigin[$decision->origin])) {
                return self::UNKNOWN_ORIGIN;
            }

            if ($decision->isAccepted() && ! $this->isLowerable($changeByOrigin[$decision->origin])) {
                return self::UNSUPPORTED_CHANGE;
            }
        }

        return null;
    }

    private function isLowerable(NodeChangeType $change): bool
    {
        return match ($change) {
            NodeChangeType::Added,
            NodeChangeType::Removed,
            NodeChangeType::Moved,
            NodeChangeType::StructureUpdated,
            NodeChangeType::ContentUpdated => true,
            default => false,
        };
    }
}
