<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Cloning\Contracts;

use Closure;

/**
 * A minimal transaction boundary for the application service (LT-3).
 *
 * Layer: Translation (contract). Keeps the framework transaction abstraction at the
 * application-service boundary and out of the domain value objects, and keeps the
 * service unit-testable. The callback runs inside one transaction; any thrown
 * exception rolls back all writes.
 *
 * @see docs/LT-3-TRANSACTION-BOUNDARY-GOVERNANCE.md
 */
interface TransactionRunnerInterface
{
    /**
     * @template T
     * @param  Closure():T  $work
     * @return T
     */
    public function run(Closure $work): mixed;
}
