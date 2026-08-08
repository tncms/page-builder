<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Execution\Mutation;

use RuntimeException;

/**
 * A pure structural fault raised by the mutation engine (LT-7B2).
 *
 * Layer: Translation (mutation engine). Carries a typed `ApplyFault` and the offending origin (a stable
 * id string, never content). It signals that no target byte was produced — the engine returns `T′` only
 * on complete success, so an in-flight apply that faults yields no partial document. The LT-7B3 service
 * normalizes this to a typed outcome; the engine itself surfaces no `SynchronizationExecutionOutcome`.
 */
final class OperationApplicationException extends RuntimeException
{
    public function __construct(
        public readonly ApplyFault $fault,
        public readonly string $origin,
    ) {
        parent::__construct("Operation apply fault: {$fault->value} at origin {$origin}");
    }
}
