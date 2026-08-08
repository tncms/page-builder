<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Recovery;

/**
 * The deterministic verdict of a recovery plan (LT-9D, per INV-LT-9D-05).
 *
 * Layer: Translation (recovery / decision). Derived purely from eligibility and the detected conflicts:
 *
 *  - {@see Recoverable}      — eligible and no conflicts; recovery may proceed on confirmation.
 *  - {@see RequiresOverride} — eligible, but soft conflicts are present; proceeding needs an explicit override.
 *  - {@see Blocked}          — ineligible, or a blocking conflict is present; recovery may not proceed.
 *
 * Identical inputs always produce the same verdict (INV-LT-9D-05). The verdict never grants execution on
 * its own — execution additionally requires explicit confirmation (INV-LT-9D-02).
 */
enum RecoveryDecision: string
{
    case Recoverable = 'recoverable';

    case RequiresOverride = 'requires_override';

    case Blocked = 'blocked';

    /**
     * Derive the verdict from eligibility + conflicts. Pure and total.
     *
     * @param  array<int, RecoveryConflict>  $conflicts
     */
    public static function derive(RecoveryEligibility $eligibility, array $conflicts): self
    {
        if (! $eligibility->isEligible()) {
            return self::Blocked;
        }

        foreach ($conflicts as $conflict) {
            if ($conflict->isBlocking()) {
                return self::Blocked;
            }
        }

        return $conflicts === [] ? self::Recoverable : self::RequiresOverride;
    }

    /** Whether recovery may proceed given whether an override was granted. */
    public function canProceed(bool $overrideGranted): bool
    {
        return match ($this) {
            self::Recoverable => true,
            self::RequiresOverride => $overrideGranted,
            self::Blocked => false,
        };
    }
}
