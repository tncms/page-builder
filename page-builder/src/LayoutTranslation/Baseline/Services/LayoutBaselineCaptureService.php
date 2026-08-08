<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Baseline\Services;

use Plugins\PageBuilder\LayoutTranslation\Baseline\BaselineFingerprint;
use Plugins\PageBuilder\LayoutTranslation\Baseline\Contracts\LayoutBaselineRepositoryInterface;
use Plugins\PageBuilder\LayoutTranslation\Baseline\LayoutBaseline;
use Plugins\PageBuilder\LayoutTranslation\Baseline\Results\BaselineCaptureOutcome;
use Plugins\PageBuilder\LayoutTranslation\Baseline\Results\BaselineCaptureResult;
use Plugins\PageBuilder\LayoutTranslation\Cloning\Contracts\SourceLayoutDocumentReaderInterface;
use Plugins\PageBuilder\LayoutTranslation\Cloning\Contracts\TransactionRunnerInterface;
use Plugins\PageBuilder\LayoutTranslation\Correspondence\NodeCorrespondence;
use Plugins\PageBuilder\LayoutTranslation\Identity\PageTranslationKey;
use Plugins\PageBuilder\LayoutTranslation\LayoutTranslationConfig;
use Plugins\PageBuilder\LayoutTranslation\Persistence\Contracts\LayoutTranslationBindingReaderInterface;
use Plugins\PageBuilder\Models\PageBuilderLayout;
use Plugins\PageBuilder\Support\LayoutStore;
use TheNguyen\CMS\Localization\Contracts\LanguageConfigurationContract;
use Throwable;

/**
 * Captures a target's source baseline and stamps its structural correspondence
 * (LT-4). The FIRST authoritative provenance model.
 *
 * Layer: Translation (baseline / application service). Flag-gated and atomic: in one
 * transaction it stamps the target's node correspondence (`data['_origin']`, target
 * only — the source is never mutated) and records the content-addressed source
 * baseline. It computes no diff and performs no synchronization (LT-5/6). It never
 * mutates the source and returns a typed result. It owns the transaction; the
 * baseline repository participates.
 */
final class LayoutBaselineCaptureService
{
    public function __construct(
        private readonly LayoutTranslationConfig $config,
        private readonly LayoutTranslationBindingReaderInterface $bindings,
        private readonly SourceLayoutDocumentReaderInterface $source,
        private readonly LayoutBaselineRepositoryInterface $baselines,
        private readonly LayoutStore $layouts,
        private readonly LanguageConfigurationContract $languages,
        private readonly TransactionRunnerInterface $transaction,
    ) {}

    public function capture(PageTranslationKey $target): BaselineCaptureResult
    {
        if (! $this->config->enabled()) {
            return BaselineCaptureResult::failure(BaselineCaptureOutcome::FeatureDisabled);
        }

        $binding = $this->bindings->findByPageTranslationKey($target);
        if ($binding === null) {
            return BaselineCaptureResult::failure(BaselineCaptureOutcome::TargetNotBound);
        }

        $default = $this->languages->normalize($this->languages->defaultLocale());
        $sourceKey = PageTranslationKey::forGroup($target->group(), $default);

        $sourceRead = $this->source->read($sourceKey);
        if (! $sourceRead->isFound()) {
            return BaselineCaptureResult::failure(BaselineCaptureOutcome::SourceUnavailable);
        }

        $sourceLayoutId = $this->resolveSourceLayoutId($sourceKey, $target);
        if ($sourceLayoutId === null) {
            return BaselineCaptureResult::failure(BaselineCaptureOutcome::SourceUnavailable);
        }

        $fingerprint = BaselineFingerprint::of($sourceRead->payload() ?? []);

        try {
            $baseline = $this->transaction->run(function () use ($binding, $target, $sourceLayoutId, $fingerprint): LayoutBaseline {
                $targetModel = PageBuilderLayout::query()->whereKey($binding->layoutId())->first();
                if ($targetModel === null) {
                    throw new BaselineIntegrityException;
                }

                // Stamp correspondence on the TARGET only (source untouched).
                $stamped = NodeCorrespondence::stamp(is_array($targetModel->draft_payload) ? $targetModel->draft_payload : []);
                $this->layouts->saveDraft((string) $targetModel->owner_type, $targetModel->owner_id, $stamped);

                return $this->baselines->record(new LayoutBaseline($target->value(), $sourceLayoutId, $fingerprint, date('c')));
            });
        } catch (BaselineIntegrityException) {
            return BaselineCaptureResult::failure(BaselineCaptureOutcome::IntegrityFault);
        } catch (Throwable) {
            return BaselineCaptureResult::failure(BaselineCaptureOutcome::PersistenceFailure);
        }

        return BaselineCaptureResult::captured($baseline);
    }

    private function resolveSourceLayoutId(PageTranslationKey $sourceKey, PageTranslationKey $target): ?int
    {
        $sourceBinding = $this->bindings->findByPageTranslationKey($sourceKey);
        if ($sourceBinding !== null) {
            return $sourceBinding->layoutId();
        }

        $row = $this->layouts->find($target->group()->ownerType, $target->group()->ownerId);

        return $row === null ? null : (int) $row->getKey();
    }
}
