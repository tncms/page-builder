<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Execution\Contracts;

use Plugins\PageBuilder\LayoutTranslation\Execution\Mutation\OperationApplicationException;
use Plugins\PageBuilder\LayoutTranslation\Execution\Operations\SyncOperationSet;

/**
 * The mutation engine boundary (LT-7B2, per INV-LT-7-OPS-02).
 *
 * Layer: Translation (execution contract). The engine's ENTIRE input surface is a target payload plus a
 * `SyncOperationSet`; it never reads the Source, Baseline, or SynchronizationPlan. It returns a new
 * target payload (`T′`) derived from the given target, or throws `OperationApplicationException` — never
 * a partial document. It performs no persistence, transaction, baseline advance, history, or diagnostics.
 */
interface OperationApplierInterface
{
    /**
     * Apply the operations to a copy of the target and return the resulting document.
     *
     * @param  array<string, mixed>  $target
     * @return array<string, mixed>
     *
     * @throws OperationApplicationException  on any unresolvable operation (nothing partial is emitted)
     */
    public function apply(array $target, SyncOperationSet $operations): array;
}
