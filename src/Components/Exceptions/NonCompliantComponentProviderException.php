<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Components\Exceptions;

use Plugins\PageBuilder\Components\ComponentComplianceReport;
use RuntimeException;

/**
 * Thrown when a component provider fails compliance and is therefore refused
 * registration (PB-XREG-1). Carries the full {@see ComponentComplianceReport} so
 * callers can surface every failed check, not just the first.
 */
final class NonCompliantComponentProviderException extends RuntimeException
{
    public function __construct(
        public readonly ComponentComplianceReport $report,
    ) {
        parent::__construct($report->summary());
    }
}
