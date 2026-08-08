<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation;

use Plugins\PageBuilder\LayoutTranslation\Contracts\LayoutTranslationStateReaderInterface;
use Plugins\PageBuilder\LayoutTranslation\State\LayoutSynchronizationState;
use Plugins\PageBuilder\LayoutTranslation\State\LayoutTranslationLifecycle;
use Plugins\PageBuilder\LayoutTranslation\State\LayoutTranslationStatus;
use Plugins\PageBuilder\Support\LayoutStore;

/**
 * The LT-1 state reader over the existing single-row storage.
 *
 * No per-locale layout storage exists in LT-1: only the source (default) locale
 * maps to the single shared `pb_layouts` row. A secondary locale therefore has no
 * layout yet (NotCreated). For the source locale the shared row's payloads decide
 * the lifecycle (Published / Draft) or NotCreated. Synchronization is always
 * NotApplicable in LT-1. Duplicate detection (InvalidReference) belongs to the
 * persistent per-locale store in a later phase — the single-row store here can
 * never return more than one.
 */
final class DefaultLayoutTranslationStateReader implements LayoutTranslationStateReaderInterface
{
    public function __construct(private readonly LayoutStore $layouts) {}

    public function statusFor(
        string $ownerType,
        int|string $ownerId,
        string $locale,
        bool $isSourceLocale,
    ): LayoutTranslationStatus {
        if (! $isSourceLocale) {
            return LayoutTranslationStatus::notCreated();
        }

        $layout = $this->layouts->find($ownerType, $ownerId);

        if ($layout === null) {
            return LayoutTranslationStatus::notCreated();
        }

        if ($layout->published_payload !== null) {
            return LayoutTranslationStatus::exists(
                LayoutTranslationLifecycle::Published,
                LayoutSynchronizationState::NotApplicable,
            );
        }

        if ($layout->draft_payload !== null) {
            return LayoutTranslationStatus::exists(
                LayoutTranslationLifecycle::Draft,
                LayoutSynchronizationState::NotApplicable,
            );
        }

        return LayoutTranslationStatus::notCreated();
    }
}
