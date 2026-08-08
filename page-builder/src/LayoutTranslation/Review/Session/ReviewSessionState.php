<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Review\Session;

/**
 * The lifecycle state of a review session (LT-8B, per LT-8A-REVIEW-WORKSPACE-ARCHITECTURE §2.2).
 *
 * Layer: Translation (review session). A closed set. `InReview` and `Paused` are working states;
 * `Submitting` is transient during the single coordinator call; the rest are terminal. A session emits at
 * most one execution command and is spent on any terminal state (INV-LT-8A-04).
 */
enum ReviewSessionState: string
{
    case Opening = 'opening';

    case Blocked = 'blocked';

    case InReview = 'in_review';

    case Paused = 'paused';

    case Submitting = 'submitting';

    case Executed = 'executed';

    case NoOp = 'no_op';

    case Refused = 'refused';

    case Abandoned = 'abandoned';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Executed, self::NoOp, self::Refused, self::Abandoned => true,
            default => false,
        };
    }

    public function isWorking(): bool
    {
        return $this === self::InReview || $this === self::Paused;
    }
}
