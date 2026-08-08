<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Execution\Services;

use Plugins\PageBuilder\LayoutTranslation\Baseline\BaselineFingerprint;
use Plugins\PageBuilder\LayoutTranslation\Cloning\Contracts\TransactionRunnerInterface;
use Plugins\PageBuilder\LayoutTranslation\Execution\Commands\ExecuteSynchronizationCommand;
use Plugins\PageBuilder\LayoutTranslation\Execution\Contracts\BaselineAdvancerInterface;
use Plugins\PageBuilder\LayoutTranslation\Execution\Contracts\ExecutionEligibilityInterface;
use Plugins\PageBuilder\LayoutTranslation\Execution\Contracts\OperationApplierInterface;
use Plugins\PageBuilder\LayoutTranslation\Execution\Contracts\SyncHistoryRecorderInterface;
use Plugins\PageBuilder\LayoutTranslation\Execution\Contracts\SynchronizationCoordinatorInterface;
use Plugins\PageBuilder\LayoutTranslation\Execution\Contracts\SynchronizationStateLoaderInterface;
use Plugins\PageBuilder\LayoutTranslation\Execution\Contracts\TargetDraftWriterInterface;
use Plugins\PageBuilder\LayoutTranslation\Execution\History\SyncHistoryRecord;
use Plugins\PageBuilder\LayoutTranslation\Execution\Mutation\OperationApplicationException;
use Plugins\PageBuilder\LayoutTranslation\Execution\Operations\OperationBuilder;
use Plugins\PageBuilder\LayoutTranslation\Execution\Operations\OperationLoweringException;
use Plugins\PageBuilder\LayoutTranslation\Execution\Results\SynchronizationExecutionOutcome;
use Plugins\PageBuilder\LayoutTranslation\Execution\Results\SynchronizationExecutionResult;
use Plugins\PageBuilder\LayoutTranslation\Execution\Validation\AcceptanceValidator;
use Plugins\PageBuilder\LayoutTranslation\Plan\SynchronizationDecision;
use Plugins\PageBuilder\LayoutTranslation\Plan\SynchronizationPlan;
use Plugins\PageBuilder\Package\DesignPackageManifest;
use Throwable;

/**
 * The synchronization coordinator (LT-7B3, per LT-7-EXECUTION-ARCHITECTURE §1 and
 * LT-7-FINAL-GOVERNANCE).
 *
 * Layer: Translation (execution orchestration). It OWNS the whole workflow: eligibility, loading,
 * provenance verification, operation lowering, mutation, persistence, baseline advance, history, the
 * transaction, and the typed result. Cheap eligibility and pure acceptance validation run before the
 * transaction; then, inside one transaction under the state lock, provenance is verified EXACTLY ONCE
 * before any mutation (INV-LT-7-EXEC-01), operations are lowered and applied by the pure engine, and the
 * target draft, advanced baseline, and one append-only history row commit together or not at all
 * (LT-7-TRANSACTION-GOVERNANCE). The mutation engine remains unaware of all of this (INV-LT-7-MUT-01/02).
 * Every failure is normalized to a typed outcome; no raw exception, SQL, model or row escapes.
 */
final class SynchronizationCoordinator implements SynchronizationCoordinatorInterface
{
    public function __construct(
        private readonly ExecutionEligibilityInterface $eligibility,
        private readonly SynchronizationStateLoaderInterface $loader,
        private readonly AcceptanceValidator $acceptanceValidator,
        private readonly OperationBuilder $operationBuilder,
        private readonly OperationApplierInterface $applier,
        private readonly TargetDraftWriterInterface $writer,
        private readonly BaselineAdvancerInterface $baseline,
        private readonly SyncHistoryRecorderInterface $history,
        private readonly TransactionRunnerInterface $transaction,
    ) {}

    public function execute(ExecuteSynchronizationCommand $command): SynchronizationExecutionResult
    {
        if (! $this->eligibility->featureEnabled()) {
            return SynchronizationExecutionResult::ineligible(SynchronizationExecutionOutcome::FeatureDisabled);
        }
        if (! $this->eligibility->supportsSync($command->target)) {
            return SynchronizationExecutionResult::ineligible(SynchronizationExecutionOutcome::CapabilityUnsupported);
        }

        $invalid = $this->acceptanceValidator->validate($command->plan, $command->acceptance);
        if ($invalid !== null) {
            return SynchronizationExecutionResult::failure(SynchronizationExecutionOutcome::AcceptanceInvalid, $invalid);
        }

        // Nothing to apply → no transaction, no write, no baseline advance, no history.
        if ($command->plan->isEmpty() || $command->acceptance->acceptedOrigins() === []) {
            return SynchronizationExecutionResult::noop();
        }

        try {
            $result = $this->transaction->run(fn (): array => $this->run($command));
        } catch (ExecutionConflictException $e) {
            return SynchronizationExecutionResult::failure($e->outcome, $e->detail);
        } catch (Throwable) {
            return SynchronizationExecutionResult::failure(SynchronizationExecutionOutcome::PersistenceFailure);
        }

        return SynchronizationExecutionResult::executed(
            (int) $result['applied'],
            (int) $result['skipped'],
            (string) $result['fingerprint'],
            (int) $result['historyId'],
        );
    }

    /**
     * The atomic body: load under lock → verify provenance once → lower → mutate → persist → advance →
     * history. Any abort throws {@see ExecutionConflictException} and rolls everything back.
     *
     * @return array{applied:int, skipped:int, fingerprint:string, historyId:int}
     */
    private function run(ExecuteSynchronizationCommand $command): array
    {
        $state = $this->loader->loadForUpdate($command->target);

        if (! $state->isBound()) {
            throw new ExecutionConflictException(SynchronizationExecutionOutcome::TargetNotBound);
        }
        if (! $state->hasBaseline()) {
            throw new ExecutionConflictException(SynchronizationExecutionOutcome::BaselineMissing);
        }
        if (! $state->hasSource()) {
            throw new ExecutionConflictException(SynchronizationExecutionOutcome::SourceUnavailable);
        }
        if (! $state->hasTarget()) {
            throw new ExecutionConflictException(SynchronizationExecutionOutcome::TargetUnavailable);
        }

        $source = $state->sourcePayload();
        $target = $state->targetPayload();

        // INV-LT-7-EXEC-01: provenance verified EXACTLY ONCE, under the lock, before any mutation.
        $drift = $command->provenance->verify(
            (string) $state->baselineFingerprint(),
            BaselineFingerprint::of($source),
            BaselineFingerprint::of($target),
        );
        if ($drift !== null) {
            throw new ExecutionConflictException(SynchronizationExecutionOutcome::PlanStale, $drift->value);
        }

        try {
            $operations = $this->operationBuilder->build($command->plan, $command->acceptance, $source);
        } catch (OperationLoweringException $e) {
            throw new ExecutionConflictException(SynchronizationExecutionOutcome::AcceptanceInvalid, $e->reason);
        }

        // Mutation (LT-7B2) — the first target byte is produced here, only after provenance passed.
        try {
            $targetPrime = $this->applier->apply($target, $operations);
        } catch (OperationApplicationException $e) {
            throw new ExecutionConflictException(SynchronizationExecutionOutcome::CorrespondenceFault, $e->fault->value);
        }

        // Persist target + advance baseline + append history — atomic with this transaction.
        $this->writer->saveDraft((string) $state->ownerType(), $state->ownerId() ?? '', $targetPrime);

        $newFingerprint = BaselineFingerprint::of($source);
        $this->baseline->advance($command->target, (int) $state->sourceLayoutId(), $newFingerprint);

        $applied = $operations->count();
        $skipped = count($command->plan->items()) - $applied;

        $historyId = $this->history->record($this->historyRecord(
            $command,
            (string) $state->baselineFingerprint(),
            $newFingerprint,
            (int) $state->sourceLayoutId(),
            $applied,
            $skipped,
        ));

        return ['applied' => $applied, 'skipped' => $skipped, 'fingerprint' => $newFingerprint, 'historyId' => $historyId];
    }

    private function historyRecord(ExecuteSynchronizationCommand $command, string $before, string $after, int $sourceLayoutId, int $applied, int $skipped): SyncHistoryRecord
    {
        $appliedItems = [];
        $skippedItems = [];

        foreach ($command->plan->items() as $item) {
            $decision = $command->acceptance->decisionFor($item->origin)?->decision ?? SynchronizationDecision::KeepTarget;
            $entry = ['origin' => $item->origin, 'source_change' => $item->sourceChange->value, 'decision' => $decision->value];

            if ($decision === SynchronizationDecision::AcceptSource) {
                $appliedItems[] = $entry;
            } else {
                $skippedItems[] = $entry;
            }
        }

        return new SyncHistoryRecord(
            $command->target->value(),
            $command->actor,
            $this->planFingerprint($command->plan),
            $before,
            $after,
            $sourceLayoutId,
            $applied,
            $skipped,
            $appliedItems,
            $skippedItems,
        );
    }

    private function planFingerprint(SynchronizationPlan $plan): string
    {
        return BaselineFingerprint::ALGO.'-'.hash(BaselineFingerprint::ALGO, DesignPackageManifest::canonicalJson($plan->toArray()));
    }
}
