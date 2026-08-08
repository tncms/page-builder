<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation;

use InvalidArgumentException;
use Plugins\PageBuilder\LayoutTranslation\Identity\LayoutKey;
use Plugins\PageBuilder\LayoutTranslation\Identity\PageTranslationKey;
use Plugins\PageBuilder\LayoutTranslation\Identity\TranslationGroupKey;
use Plugins\PageBuilder\LayoutTranslation\State\LayoutTranslationExistence;
use Plugins\PageBuilder\LayoutTranslation\State\LayoutSynchronizationState;
use Plugins\PageBuilder\LayoutTranslation\State\LayoutTranslationLifecycle;
use Plugins\PageBuilder\LayoutTranslation\State\LayoutTranslationStatus;

/**
 * The canonical, LOCKED value object of the layout translation domain — LT-1.
 *
 *     Translation Group  →  Page Translation  →  Page Builder Layout
 *
 * A layout is not owned by a Page; it belongs to a Page Translation within a
 * Translation Group. This descriptor is that relationship resolved into one
 * immutable value: three identity keys, the multi-dimensional
 * {@see LayoutTranslationStatus}, the {@see LayoutTranslationCapabilities}, and a
 * relationship-validity marker.
 *
 * It is a pure semantic value object — final, immutable, constructor-validated,
 * with no setters, no mutable state, no persistence logic, no service references,
 * no Eloquent models, no query builders and no workflow execution. Storage
 * entities never appear here. Its semantic contract is frozen: future phases may
 * only extend it through additive optional metadata or dedicated child value
 * objects, never by changing this responsibility.
 *
 * `fault` is a relationship-validity concern (null = a well-formed, translatable
 * relationship), distinct from the business status dimensions.
 */
final class LayoutTranslationDescriptor
{
    private function __construct(
        public readonly TranslationGroupKey $group,
        public readonly PageTranslationKey $translation,
        public readonly LayoutKey $layout,
        public readonly string $sourceLocale,
        public readonly bool $isSourceLocale,
        public readonly LayoutTranslationStatus $status,
        public readonly LayoutTranslationCapabilities $capabilities,
        public readonly ?LayoutTranslationResolutionFault $fault,
    ) {
        if (trim($sourceLocale) === '') {
            throw new InvalidArgumentException('A descriptor requires a non-empty source locale.');
        }

        if (! $translation->group()->equals($group)) {
            throw new InvalidArgumentException('The page translation must belong to the descriptor group.');
        }

        if (! $layout->translation()->equals($translation)) {
            throw new InvalidArgumentException('The layout key must reference the descriptor page translation.');
        }

        if ($isSourceLocale !== ($translation->locale() === $sourceLocale)) {
            throw new InvalidArgumentException('isSourceLocale must agree with the translation locale vs the source locale.');
        }
    }

    public static function resolved(
        TranslationGroupKey $group,
        PageTranslationKey $translation,
        LayoutKey $layout,
        string $sourceLocale,
        bool $isSourceLocale,
        LayoutTranslationStatus $status,
        LayoutTranslationCapabilities $capabilities,
    ): self {
        return new self($group, $translation, $layout, $sourceLocale, $isSourceLocale, $status, $capabilities, null);
    }

    public static function unresolved(
        TranslationGroupKey $group,
        PageTranslationKey $translation,
        LayoutKey $layout,
        string $sourceLocale,
        bool $isSourceLocale,
        LayoutTranslationResolutionFault $fault,
        LayoutTranslationCapabilities $capabilities,
    ): self {
        return new self(
            $group,
            $translation,
            $layout,
            $sourceLocale,
            $isSourceLocale,
            LayoutTranslationStatus::notCreated(),
            $capabilities,
            $fault,
        );
    }

    // ---- Identity -------------------------------------------------------

    public function group(): TranslationGroupKey
    {
        return $this->group;
    }

    public function translation(): PageTranslationKey
    {
        return $this->translation;
    }

    public function layout(): LayoutKey
    {
        return $this->layout;
    }

    public function ownerType(): string
    {
        return $this->group->ownerType;
    }

    public function ownerId(): string
    {
        return $this->group->ownerId;
    }

    public function locale(): string
    {
        return $this->translation->locale();
    }

    // ---- State ----------------------------------------------------------

    public function status(): LayoutTranslationStatus
    {
        return $this->status;
    }

    public function existence(): LayoutTranslationExistence
    {
        return $this->status->existence();
    }

    public function lifecycle(): ?LayoutTranslationLifecycle
    {
        return $this->status->lifecycle();
    }

    public function synchronization(): LayoutSynchronizationState
    {
        return $this->status->synchronization();
    }

    public function hasLayout(): bool
    {
        return $this->status->hasLayout();
    }

    // ---- Validity + capabilities ---------------------------------------

    public function isResolvable(): bool
    {
        return $this->fault === null;
    }

    public function fault(): ?LayoutTranslationResolutionFault
    {
        return $this->fault;
    }

    public function isSourceLocale(): bool
    {
        return $this->isSourceLocale;
    }

    public function capabilities(): LayoutTranslationCapabilities
    {
        return $this->capabilities;
    }

    public function supportsClone(): bool
    {
        return $this->capabilities->supportsClone();
    }

    public function supportsSync(): bool
    {
        return $this->capabilities->supportsSync();
    }

    public function supportsDiff(): bool
    {
        return $this->capabilities->supportsDiff();
    }

    public function supportsReview(): bool
    {
        return $this->capabilities->supportsReview();
    }

    public function supportsPreview(): bool
    {
        return $this->capabilities->supportsPreview();
    }

    public function supportsPublish(): bool
    {
        return $this->capabilities->supportsPublish();
    }

    // ---- Equality + serialization --------------------------------------

    public function equals(self $other): bool
    {
        return $this->group->equals($other->group)
            && $this->translation->equals($other->translation)
            && $this->layout->equals($other->layout)
            && $this->sourceLocale === $other->sourceLocale
            && $this->isSourceLocale === $other->isSourceLocale
            && $this->status->equals($other->status)
            && $this->capabilities->toArray() === $other->capabilities->toArray()
            && $this->fault === $other->fault;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'identity' => [
                'translation_group' => $this->group->value(),
                'page_translation' => $this->translation->value(),
                'layout' => $this->layout->value(),
            ],
            'owner_type' => $this->ownerType(),
            'owner_id' => $this->ownerId(),
            'locale' => $this->locale(),
            'source_locale' => $this->sourceLocale,
            'is_source_locale' => $this->isSourceLocale,
            'status' => $this->status->toArray(),
            'resolvable' => $this->isResolvable(),
            'fault' => $this->fault?->value,
            'capabilities' => $this->capabilities->toArray(),
        ];
    }
}
