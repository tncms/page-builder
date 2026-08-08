<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Editor\Translation;

use Plugins\PageBuilder\LayoutTranslation\Baseline\Services\LayoutBaselineCaptureService;
use Plugins\PageBuilder\LayoutTranslation\Cloning\Commands\CloneLayoutTranslationCommand;
use Plugins\PageBuilder\LayoutTranslation\Cloning\Contracts\LayoutTranslationCloneServiceInterface;
use Plugins\PageBuilder\LayoutTranslation\Cloning\Results\LayoutTranslationCloneOutcome;
use Plugins\PageBuilder\LayoutTranslation\Identity\PageTranslationKey;
use Plugins\PageBuilder\Support\LayoutStore;

/**
 * Clone-on-first-open provisioning for a translated layout (LT-M1B).
 *
 * Idempotent orchestration ABOVE the frozen LT pipeline — it invokes the frozen
 * clone service (LT-3, which atomically creates the target `pb_layouts` row +
 * binding + initial status) and then the frozen baseline capture (LT-4). It never
 * mutates the source layout and never modifies an LT contract.
 *
 * Provisioning is a first-OPEN concern only. It is a no-op for the source locale
 * (the canonical layout is the source) and for an already-provisioned target
 * (a row already exists). On a non-recoverable clone failure it fails closed —
 * the caller must NOT open the target editor against the source layout.
 */
final class FirstOpenLayoutProvisioner
{
    public function __construct(
        private readonly LayoutStore $layouts,
        private readonly LayoutTranslationCloneServiceInterface $cloneService,
        private readonly LayoutBaselineCaptureService $baselines,
    ) {}

    public function ensure(EffectiveLayoutOwner $effective, ?string $actor): LayoutProvisionResult
    {
        // Source locale: the canonical layout IS the source — nothing to provision.
        if ($effective->isSourceLocale) {
            return LayoutProvisionResult::sourceLocale();
        }

        // Idempotent: an existing target row means this locale is already provisioned.
        if ($this->layouts->find($effective->ownerType, $effective->ownerId) !== null) {
            return LayoutProvisionResult::alreadyProvisioned();
        }

        // First open: clone the source layout into the target owner. The frozen
        // service owns the atomic { target row + binding + status } transaction.
        $source = PageTranslationKey::forGroup($effective->group, $effective->sourceLocale);
        $command = new CloneLayoutTranslationCommand($effective->group, $source, $effective->translation, $actor);
        $result = $this->cloneService->cloneTranslation($command);

        if ($result->isCreated()) {
            // Capture the initial provenance baseline (frozen LT-4). It needs the
            // binding the clone just created; not fatal to isolation if it declines.
            $captured = $this->baselines->capture($effective->translation)->isCaptured();

            return LayoutProvisionResult::cloned($result->binding()?->layoutId(), $captured);
        }

        // A concurrent first-open may have won the race — treat as provisioned.
        if (in_array($result->outcome(), [
            LayoutTranslationCloneOutcome::AlreadyCloned,
            LayoutTranslationCloneOutcome::TargetLayoutConflict,
            LayoutTranslationCloneOutcome::ConcurrentConflict,
        ], true)) {
            return LayoutProvisionResult::alreadyProvisioned();
        }

        // Anything else (feature disabled, source unavailable, persistence fault):
        // fail closed. Never open the target editor against the source layout.
        throw EditorOwnerResolutionException::cloneFailed($result->outcome()->value);
    }
}
