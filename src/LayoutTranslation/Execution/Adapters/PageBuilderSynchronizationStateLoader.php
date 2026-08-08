<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Execution\Adapters;

use Plugins\PageBuilder\LayoutTranslation\Baseline\Contracts\LayoutBaselineRepositoryInterface;
use Plugins\PageBuilder\LayoutTranslation\Cloning\Contracts\SourceLayoutDocumentReaderInterface;
use Plugins\PageBuilder\LayoutTranslation\Execution\Contracts\SynchronizationStateLoaderInterface;
use Plugins\PageBuilder\LayoutTranslation\Execution\Loading\LoadedSynchronizationState;
use Plugins\PageBuilder\LayoutTranslation\Identity\PageTranslationKey;
use Plugins\PageBuilder\LayoutTranslation\Persistence\Contracts\LayoutTranslationBindingReaderInterface;
use Plugins\PageBuilder\Models\PageBuilderLayout;
use Plugins\PageBuilder\Support\LayoutStore;
use TheNguyen\CMS\Localization\Contracts\LanguageConfigurationContract;

/**
 * Production loader of canonical synchronization state (LT-7C).
 *
 * Layer: Translation (execution adapter). Composes the frozen LT-2 binding reader, LT-4 baseline store,
 * LT-3 source reader, and the generic `LayoutStore` into one immutable {@see LoadedSynchronizationState}.
 * `loadForUpdate` locks the bound `pb_layouts` row (`lockForUpdate`) so the coordinator can verify
 * provenance exactly once under the lock (INV-LT-7-EXEC-01); `load` is lock-free for diagnostics. It
 * reads the SOURCE via the default-locale document (published → draft) and never mutates it (INV-LT-01);
 * the TARGET payload and owner coordinates come from the bound row. Returns domain data only — no Eloquent
 * model crosses the boundary.
 */
final class PageBuilderSynchronizationStateLoader implements SynchronizationStateLoaderInterface
{
    public function __construct(
        private readonly LayoutTranslationBindingReaderInterface $bindings,
        private readonly LayoutBaselineRepositoryInterface $baselines,
        private readonly SourceLayoutDocumentReaderInterface $source,
        private readonly LayoutStore $layouts,
        private readonly LanguageConfigurationContract $languages,
    ) {}

    public function loadForUpdate(PageTranslationKey $target): LoadedSynchronizationState
    {
        return $this->loadInternal($target, true);
    }

    public function load(PageTranslationKey $target): LoadedSynchronizationState
    {
        return $this->loadInternal($target, false);
    }

    private function loadInternal(PageTranslationKey $target, bool $lock): LoadedSynchronizationState
    {
        $binding = $this->bindings->findByPageTranslationKey($target);
        if ($binding === null) {
            return LoadedSynchronizationState::unbound();
        }

        $baseline = $this->baselines->find($target->value());

        $default = $this->languages->normalize($this->languages->defaultLocale());
        $sourceKey = PageTranslationKey::forGroup($target->group(), $default);
        $read = $this->source->read($sourceKey);
        $sourcePayload = $read->isFound() ? $read->payload() : null;
        $sourceLayoutId = $this->resolveSourceLayoutId($sourceKey, $target);

        $query = PageBuilderLayout::query()->whereKey($binding->layoutId());
        if ($lock) {
            $query->lockForUpdate();
        }
        $targetRow = $query->first();

        $targetPayload = null;
        $ownerType = null;
        $ownerId = null;
        if ($targetRow !== null) {
            $targetPayload = is_array($targetRow->draft_payload) ? $targetRow->draft_payload : null;
            $ownerType = (string) $targetRow->owner_type;
            $ownerId = $targetRow->owner_id;
        }

        return new LoadedSynchronizationState(
            true,
            $baseline?->fingerprint(),
            $sourcePayload,
            $targetPayload,
            $sourceLayoutId,
            $ownerType,
            $ownerId,
        );
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
