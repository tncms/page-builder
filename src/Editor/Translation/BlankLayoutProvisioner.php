<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Editor\Translation;

use Plugins\PageBuilder\LayoutTranslation\Baseline\Services\LayoutBaselineCaptureService;
use Plugins\PageBuilder\LayoutTranslation\Cloning\Contracts\TargetLayoutWriterInterface;
use Plugins\PageBuilder\LayoutTranslation\Cloning\Contracts\TargetOwnerResolverInterface;
use Plugins\PageBuilder\LayoutTranslation\Cloning\Contracts\TransactionRunnerInterface;
use Plugins\PageBuilder\LayoutTranslation\Identity\LayoutKey;
use Plugins\PageBuilder\LayoutTranslation\Persistence\Contracts\LayoutTranslationBindingRepositoryInterface;
use Plugins\PageBuilder\LayoutTranslation\Persistence\Results\PersistedLayoutTranslationBinding;
use Plugins\PageBuilder\LayoutTranslation\State\LayoutSynchronizationState;
use Plugins\PageBuilder\LayoutTranslation\State\LayoutTranslationLifecycle;
use Plugins\PageBuilder\LayoutTranslation\State\LayoutTranslationStatus;
use Plugins\PageBuilder\Support\LayoutNormalizer;
use Plugins\PageBuilder\Support\LayoutStore;
use Throwable;

/**
 * Provisions a BLANK translated layout on the user's explicit decision
 * (PB-CORE-AUTHORITY-REMEDIATION-R1, C).
 *
 * Used only when the source language has no Page Builder content to copy and the
 * user chose "Create blank translation". It is the additive blank counterpart of
 * the frozen LT-3 clone: it consumes the SAME frozen seams (target owner resolver,
 * target layout writer, binding repository, transaction runner) and the frozen
 * LT-4 baseline capture, and never modifies an LT contract or the source.
 *
 * The target row, its binding (Draft / InSync) are written in one transaction; the
 * caller's outer transaction (the Core translation create) makes the whole request
 * atomic. The layout starts as an empty draft and is never published. The baseline
 * is captured when a source document exists (`empty`); with an `absent` source
 * there is no source to fingerprint, so LT-4 declines and none is recorded.
 *
 * Idempotent: a target that already has a layout row is left untouched. Any
 * failure throws, so nothing of the unit survives.
 */
final class BlankLayoutProvisioner
{
    public function __construct(
        private readonly LayoutStore $layouts,
        private readonly TargetOwnerResolverInterface $targetOwners,
        private readonly TargetLayoutWriterInterface $targetWriter,
        private readonly LayoutTranslationBindingRepositoryInterface $bindings,
        private readonly TransactionRunnerInterface $transaction,
        private readonly LayoutBaselineCaptureService $baselines,
    ) {}

    /**
     * @throws EditorOwnerResolutionException when the blank layout cannot be provisioned
     */
    public function ensureBlank(EffectiveLayoutOwner $effective, ?string $actor): LayoutProvisionResult
    {
        if ($effective->isSourceLocale) {
            return LayoutProvisionResult::sourceLocale();
        }

        if ($this->layouts->find($effective->ownerType, $effective->ownerId) !== null) {
            return LayoutProvisionResult::alreadyProvisioned();
        }

        $owner = $this->targetOwners->resolve($effective->translation);
        if ($owner === null) {
            throw EditorOwnerResolutionException::translationNotFound($effective->locale);
        }

        try {
            $layoutId = $this->transaction->run(function () use ($effective, $owner, $actor): int {
                if ($this->bindings->findByPageTranslationKey($effective->translation) !== null) {
                    throw EditorOwnerResolutionException::blankProvisionFailed('already_bound');
                }

                $layoutId = $this->targetWriter->createDraftLayoutForOwner(
                    $owner,
                    ['schema' => LayoutNormalizer::SCHEMA, 'sections' => []],
                    $actor,
                );

                $result = $this->bindings->createBinding(new PersistedLayoutTranslationBinding(
                    $effective->group,
                    $effective->translation,
                    LayoutKey::forTranslation($effective->translation),
                    $layoutId,
                    LayoutTranslationStatus::exists(LayoutTranslationLifecycle::Draft, LayoutSynchronizationState::InSync),
                ));

                if (! $result->isSuccessful() || $result->binding() === null) {
                    throw EditorOwnerResolutionException::blankProvisionFailed($result->outcome()->value);
                }

                return $layoutId;
            });
        } catch (EditorOwnerResolutionException $e) {
            throw $e;
        } catch (Throwable) {
            throw EditorOwnerResolutionException::blankProvisionFailed('persistence_failure');
        }

        $captured = $this->baselines->capture($effective->translation)->isCaptured();

        return LayoutProvisionResult::blank($layoutId, $captured);
    }
}
