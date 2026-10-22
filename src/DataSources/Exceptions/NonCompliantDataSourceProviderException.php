<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\DataSources\Exceptions;

use Plugins\PageBuilder\DataSources\DataSourceComplianceReport;
use RuntimeException;

/**
 * Thrown when a data-source provider fails compliance and is refused registration
 * (PB-XREG-2). Carries the full report so every failed check can be surfaced.
 */
final class NonCompliantDataSourceProviderException extends RuntimeException
{
    public function __construct(
        public readonly DataSourceComplianceReport $report,
    ) {
        parent::__construct($report->summary());
    }
}
