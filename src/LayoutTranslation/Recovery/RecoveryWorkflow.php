<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Recovery;

use Plugins\PageBuilder\LayoutTranslation\Recovery\Contracts\RecoveryCoordinatorGatewayInterface;
use Plugins\PageBuilder\LayoutTranslation\Recovery\Contracts\RecoverySourceGatewayInterface;

/**
 * The recovery orchestration that operates ABOVE the frozen Timeline Platform (LT-9D).
 *
 * Layer: Translation (recovery / orchestration). It coordinates recovery from a prior synchronization
 * outcome while preserving the append-only history model. It does two things and nothing else:
 *
 *  - {@see plan()}    — observes the candidate through the read seam, evaluates eligibility, analyzes
 *                       conflicts, derives the deterministic decision, and returns a {@see RecoveryReport}
 *                       containing the {@see RecoveryPlan}. Planning ALWAYS precedes execution (INV-LT-9D-02).
 *  - {@see execute()} — validates a confirmed {@see RecoveryExecutionRequest} and, if valid, DELEGATES to the
 *                       frozen Coordinator gateway. The Coordinator is the sole execution authority
 *                       (INV-LT-9D-03); recovery orchestrates, the Coordinator executes.
 *
 * The workflow itself never mutates history, snapshots, baselines or the Timeline Runtime. Recovery always
 * creates a NEW execution — it never edits historical execution (INV-LT-9D-01/04). It reads nothing from
 * storage directly (INV-LT-9D — recovery sources) and holds no clock or randomness, so planning is
 * deterministic (INV-LT-9D-05).
 */
final class RecoveryWorkflow
{
    public function __construct(
        private readonly RecoverySourceGatewayInterface $source,
        private readonly RecoveryEligibilityEvaluator $eligibility,
        private readonly RecoveryConflictResolver $conflicts,
        private readonly RecoveryCoordinatorGatewayInterface $coordinator,
        private readonly RecoveryPolicy $policy,
    ) {}

    /**
     * Produce the recovery plan for a request. Pure with respect to the observed candidate — it never
     * executes and never mutates (INV-LT-9D-02).
     */
    public function plan(RecoveryRequest $request): RecoveryReport
    {
        $candidate = $this->source->candidate($request);
        $eligibility = $this->eligibility->evaluate($candidate, $this->policy, $request);
        $conflicts = $this->conflicts->resolve($candidate, $this->policy, $request);
        $decision = RecoveryDecision::derive($eligibility, $conflicts);

        $plan = RecoveryPlan::build($request, $candidate, $eligibility, $conflicts, $decision);

        return RecoveryReport::of($request, $plan, $candidate);
    }

    /**
     * Execute a confirmed recovery by delegating to the frozen Coordinator. Recovery never executes
     * synchronization itself (INV-LT-9D-03) and never edits history (INV-LT-9D-01) — the Coordinator creates
     * a NEW append-only execution. A request that fails validation is refused before any delegation
     * (INV-LT-9D-02).
     */
    public function execute(RecoveryExecutionRequest $request): RecoveryExecutionResult
    {
        $validation = RecoveryValidation::for($request);
        if (! $validation->isValid()) {
            return RecoveryExecutionResult::rejected((string) $validation->reason());
        }

        return $this->coordinator->execute($request);
    }
}
