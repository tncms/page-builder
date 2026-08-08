<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Cloning\Services;

use Illuminate\Database\QueryException;
use Plugins\PageBuilder\LayoutTranslation\Cloning\Commands\CloneLayoutTranslationCommand;
use Plugins\PageBuilder\LayoutTranslation\Cloning\Contracts\LayoutTranslationCloneServiceInterface;
use Plugins\PageBuilder\LayoutTranslation\Cloning\Contracts\SourceLayoutDocumentReaderInterface;
use Plugins\PageBuilder\LayoutTranslation\Cloning\Contracts\TargetLayoutWriterInterface;
use Plugins\PageBuilder\LayoutTranslation\Cloning\Contracts\TransactionRunnerInterface;
use Plugins\PageBuilder\LayoutTranslation\Cloning\Eligibility\CloneEligibilityEvaluator;
use Plugins\PageBuilder\LayoutTranslation\Cloning\Contracts\TargetOwnerResolverInterface;
use Plugins\PageBuilder\LayoutTranslation\Cloning\Eligibility\CloneIneligibilityReason;
use Plugins\PageBuilder\LayoutTranslation\Cloning\Results\LayoutTranslationCloneOutcome;
use Plugins\PageBuilder\LayoutTranslation\Cloning\Results\LayoutTranslationCloneResult;
use Plugins\PageBuilder\LayoutTranslation\Identity\LayoutKey;
use Plugins\PageBuilder\LayoutTranslation\Persistence\Contracts\LayoutTranslationBindingRepositoryInterface;
use Plugins\PageBuilder\LayoutTranslation\Persistence\Results\BindingWriteOutcome;
use Plugins\PageBuilder\LayoutTranslation\Persistence\Results\PersistedLayoutTranslationBinding;
use Plugins\PageBuilder\LayoutTranslation\State\LayoutSynchronizationState;
use Plugins\PageBuilder\LayoutTranslation\State\LayoutTranslationLifecycle;
use Plugins\PageBuilder\LayoutTranslation\State\LayoutTranslationStatus;
use Plugins\PageBuilder\Support\LayoutStore;
use Throwable;

/**
 * The application clone service — the atomic source→target write workflow (LT-3).
 *
 * Layer: Translation (application service). It OWNS the transaction: resolve →
 * evaluate eligibility → read source → atomic { create target document + create
 * binding + persist initial status } → typed result. Repositories participate but
 * never orchestrate. It never publishes, baselines, diffs, synchronizes, or
 * translates text. Retries re-resolve canonical persisted state; database
 * uniqueness is the final concurrency guard, normalized to a typed outcome here.
 * No frozen LT-1/LT-2 contract is modified.
 */
final class LayoutTranslationCloneService implements LayoutTranslationCloneServiceInterface
{
    public function __construct(
        private readonly CloneEligibilityEvaluator $eligibility,
        private readonly TargetOwnerResolverInterface $targetOwners,
        private readonly SourceLayoutDocumentReaderInterface $source,
        private readonly TargetLayoutWriterInterface $targetWriter,
        private readonly LayoutTranslationBindingRepositoryInterface $bindings,
        private readonly LayoutStore $layouts,
        private readonly TransactionRunnerInterface $transaction,
    ) {}

    public function cloneTranslation(CloneLayoutTranslationCommand $command): LayoutTranslationCloneResult
    {
        $eligibility = $this->eligibility->evaluate($command);
        if (! $eligibility->isEligible()) {
            return LayoutTranslationCloneResult::ineligible($this->mapReason($eligibility->reason()));
        }

        // Re-resolve canonical persisted state (retry safety — no reliance on the
        // pre-check's in-memory state).
        $targetOwner = $this->targetOwners->resolve($command->target);
        if ($targetOwner === null) {
            return LayoutTranslationCloneResult::ineligible(LayoutTranslationCloneOutcome::TargetNotFound);
        }

        $read = $this->source->read($command->source);
        if ($read->isNotFound()) {
            return LayoutTranslationCloneResult::ineligible(LayoutTranslationCloneOutcome::SourceNotFound);
        }
        if ($read->isPayloadUnavailable()) {
            return LayoutTranslationCloneResult::ineligible(LayoutTranslationCloneOutcome::SourcePayloadUnavailable);
        }

        $payload = $read->payload() ?? [];
        $layoutKey = LayoutKey::forTranslation($command->target);

        try {
            $binding = $this->transaction->run(function () use ($command, $targetOwner, $payload, $layoutKey): PersistedLayoutTranslationBinding {
                // In-transaction uniqueness re-checks (no writes yet → safe to return).
                if ($this->bindings->findByPageTranslationKey($command->target) !== null) {
                    throw new CloneConflictException(LayoutTranslationCloneOutcome::AlreadyCloned);
                }
                if ($this->layouts->find($targetOwner->ownerType(), $targetOwner->ownerId()) !== null) {
                    throw new CloneConflictException(LayoutTranslationCloneOutcome::TargetLayoutConflict);
                }

                $targetLayoutId = $this->targetWriter->createDraftLayoutForOwner($targetOwner, $payload, $command->actor);

                $persisted = new PersistedLayoutTranslationBinding(
                    $command->target->group(),
                    $command->target,
                    $layoutKey,
                    $targetLayoutId,
                    LayoutTranslationStatus::exists(LayoutTranslationLifecycle::Draft, LayoutSynchronizationState::InSync),
                );

                $result = $this->bindings->createBinding($persisted);
                if (! $result->isSuccessful() || $result->binding() === null) {
                    throw new CloneConflictException($this->mapWriteOutcome($result->outcome()));
                }

                return $result->binding();
            });
        } catch (CloneConflictException $e) {
            return LayoutTranslationCloneResult::failure($e->outcome);
        } catch (QueryException $e) {
            return LayoutTranslationCloneResult::failure($this->normalizeQueryException($e));
        } catch (Throwable) {
            return LayoutTranslationCloneResult::failure(LayoutTranslationCloneOutcome::PersistenceFailure);
        }

        return LayoutTranslationCloneResult::created($binding);
    }

    private function mapReason(?CloneIneligibilityReason $reason): LayoutTranslationCloneOutcome
    {
        return match ($reason) {
            CloneIneligibilityReason::FeatureDisabled => LayoutTranslationCloneOutcome::FeatureDisabled,
            CloneIneligibilityReason::CapabilityUnsupported => LayoutTranslationCloneOutcome::CapabilityUnsupported,
            CloneIneligibilityReason::SameTranslation => LayoutTranslationCloneOutcome::SameTranslation,
            CloneIneligibilityReason::DifferentTranslationGroup => LayoutTranslationCloneOutcome::DifferentTranslationGroup,
            CloneIneligibilityReason::TargetNotFound => LayoutTranslationCloneOutcome::TargetNotFound,
            CloneIneligibilityReason::SourceNotFound => LayoutTranslationCloneOutcome::SourceNotFound,
            CloneIneligibilityReason::SourcePayloadUnavailable => LayoutTranslationCloneOutcome::SourcePayloadUnavailable,
            CloneIneligibilityReason::TargetAlreadyBound => LayoutTranslationCloneOutcome::AlreadyCloned,
            CloneIneligibilityReason::TargetLayoutConflict => LayoutTranslationCloneOutcome::TargetLayoutConflict,
            CloneIneligibilityReason::IntegrityFault => LayoutTranslationCloneOutcome::IntegrityFault,
            default => LayoutTranslationCloneOutcome::InvalidIdentity,
        };
    }

    private function mapWriteOutcome(BindingWriteOutcome $outcome): LayoutTranslationCloneOutcome
    {
        return match ($outcome) {
            BindingWriteOutcome::AlreadyExists => LayoutTranslationCloneOutcome::AlreadyCloned,
            BindingWriteOutcome::Conflict => LayoutTranslationCloneOutcome::ConcurrentConflict,
            BindingWriteOutcome::InvalidReference, BindingWriteOutcome::InvalidIdentity => LayoutTranslationCloneOutcome::IntegrityFault,
            default => LayoutTranslationCloneOutcome::PersistenceFailure,
        };
    }

    private function normalizeQueryException(QueryException $e): LayoutTranslationCloneOutcome
    {
        $message = strtolower($e->getMessage());

        if (str_contains($message, 'unique') || str_contains($message, 'duplicate')) {
            return LayoutTranslationCloneOutcome::ConcurrentConflict;
        }

        if (str_contains($message, 'foreign key') || str_contains($message, 'foreign_key')) {
            return LayoutTranslationCloneOutcome::IntegrityFault;
        }

        return LayoutTranslationCloneOutcome::PersistenceFailure;
    }
}
