<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Recovery\Contracts;

use Plugins\PageBuilder\LayoutTranslation\Recovery\RecoveryCandidate;
use Plugins\PageBuilder\LayoutTranslation\Recovery\RecoveryRequest;

/**
 * The recovery read seam over the frozen runtime APIs (LT-9D).
 *
 * Layer: Translation (recovery / read port). Recovery NEVER reads storage directly — it observes only
 * through this seam, which assembles an immutable {@see RecoveryCandidate} by consuming the frozen Timeline,
 * Replay, Explainability, Snapshot Resolver and Synchronization History runtimes. A missing entry yields a
 * `notFound` candidate (fail-soft), never null and never a fabricated document. The concrete adapter is thin
 * wiring; the recovery runtime depends on this abstraction only, which keeps every decision deterministic and
 * unit-testable against a fake.
 */
interface RecoverySourceGatewayInterface
{
    public function candidate(RecoveryRequest $request): RecoveryCandidate;
}
