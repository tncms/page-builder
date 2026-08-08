<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\SDK\Exceptions;

use Plugins\PageBuilder\SDK\ComplianceReport;
use RuntimeException;

/**
 * Thrown when a provider fails compliance and is therefore refused registration
 * (Phase 26.49B, Rule 3: "Providers failing validation must not register.").
 *
 * Carries the full {@see ComplianceReport} so callers can surface every failed
 * check, not just the first.
 */
final class NonCompliantProviderException extends RuntimeException
{
    public function __construct(
        public readonly ComplianceReport $report,
    ) {
        parent::__construct($report->summary());
    }
}
