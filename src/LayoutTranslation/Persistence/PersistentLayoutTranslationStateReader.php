<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Persistence;

use Plugins\PageBuilder\LayoutTranslation\Contracts\LayoutTranslationStateReaderInterface;
use Plugins\PageBuilder\LayoutTranslation\DefaultLayoutTranslationStateReader;
use Plugins\PageBuilder\LayoutTranslation\Identity\PageTranslationKey;
use Plugins\PageBuilder\LayoutTranslation\Identity\TranslationGroupKey;
use Plugins\PageBuilder\LayoutTranslation\Persistence\Contracts\LayoutTranslationBindingReaderInterface;
use Plugins\PageBuilder\LayoutTranslation\State\LayoutTranslationStatus;

/**
 * Persistence-backed implementation of the frozen LT-1 state-reader seam — LT-2.
 *
 * Layer: Persistence (adapter). It backs {@see LayoutTranslationStateReaderInterface}
 * — whose contract is unchanged — with the binding store:
 *   - a persisted binding for the page translation → its persisted status;
 *   - otherwise, for the source locale → the canonical pb_layouts document status
 *     (delegated to the legacy reader) so an un-backfilled source is still correct;
 *   - otherwise → NotCreated.
 *
 * It reads only; it never creates bindings, clones, or synchronizes. It returns
 * frozen status value objects, never storage models. Corrupt persisted state is
 * already collapsed to an invalid-reference status by the repository.
 */
final class PersistentLayoutTranslationStateReader implements LayoutTranslationStateReaderInterface
{
    public function __construct(
        private readonly LayoutTranslationBindingReaderInterface $bindings,
        private readonly DefaultLayoutTranslationStateReader $documentReader,
    ) {}

    public function statusFor(
        string $ownerType,
        int|string $ownerId,
        string $locale,
        bool $isSourceLocale,
    ): LayoutTranslationStatus {
        $group = TranslationGroupKey::forOwner($ownerType, $ownerId);
        $translation = PageTranslationKey::forGroup($group, $locale);

        $binding = $this->bindings->findByPageTranslationKey($translation);
        if ($binding !== null) {
            return $binding->status();
        }

        if ($isSourceLocale) {
            // No binding yet (e.g. before backfill): the source layout document is
            // authoritative in pb_layouts.
            return $this->documentReader->statusFor($ownerType, $ownerId, $locale, $isSourceLocale);
        }

        return LayoutTranslationStatus::notCreated();
    }
}
