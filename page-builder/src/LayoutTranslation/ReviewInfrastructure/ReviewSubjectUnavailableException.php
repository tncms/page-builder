<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\ReviewInfrastructure;

use RuntimeException;

/**
 * A review subject could not be built for a target (LT-8D).
 *
 * Layer: Translation (review infrastructure). Thrown by {@see PlanningReviewInputProvider} when the target
 * is not reviewable (unbound / no baseline / no source / no target) or the baseline document is unavailable
 * or inconsistent with the stored fingerprint. The presentation layer already treats any provider failure
 * as a fail-soft blocked state (LT-8C), so this surfaces as "review not available" — never a crash.
 */
final class ReviewSubjectUnavailableException extends RuntimeException
{
    public static function notReviewable(string $reason): self
    {
        return new self("Target is not reviewable: {$reason}.");
    }

    public static function baselineDocumentUnavailable(): self
    {
        return new self('The baseline document is unavailable; a synchronization plan cannot be built yet.');
    }

    public static function baselineInconsistent(): self
    {
        return new self('The baseline document does not match the stored baseline fingerprint.');
    }
}
