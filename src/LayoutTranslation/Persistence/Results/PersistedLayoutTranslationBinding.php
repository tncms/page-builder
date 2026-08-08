<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Persistence\Results;

use InvalidArgumentException;
use Plugins\PageBuilder\LayoutTranslation\Identity\LayoutKey;
use Plugins\PageBuilder\LayoutTranslation\Identity\PageTranslationKey;
use Plugins\PageBuilder\LayoutTranslation\Identity\TranslationGroupKey;
use Plugins\PageBuilder\LayoutTranslation\State\LayoutTranslationStatus;

/**
 * The domain representation of one persisted translation binding — LT-2.
 *
 * Layer: Persistence / Result (domain contract).
 *
 * This is what the binding reader and repository RETURN — never an Eloquent model,
 * query builder, raw row or untyped array. It composes the frozen LT-1 identity
 * value objects, the `pb_layouts.id` it binds to, and the frozen multi-dimensional
 * status. Immutable and constructor-validated: the three identity keys must cohere
 * (Translation Group → Page Translation → Layout).
 */
final class PersistedLayoutTranslationBinding
{
    public function __construct(
        public readonly TranslationGroupKey $group,
        public readonly PageTranslationKey $translation,
        public readonly LayoutKey $layout,
        public readonly int $layoutId,
        public readonly LayoutTranslationStatus $status,
    ) {
        if (! $translation->group()->equals($group)) {
            throw new InvalidArgumentException('The page translation must belong to the binding group.');
        }

        if (! $layout->translation()->equals($translation)) {
            throw new InvalidArgumentException('The layout key must reference the binding page translation.');
        }

        if ($layoutId <= 0) {
            throw new InvalidArgumentException('A binding must reference a positive pb_layouts id.');
        }
    }

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

    public function layoutId(): int
    {
        return $this->layoutId;
    }

    public function status(): LayoutTranslationStatus
    {
        return $this->status;
    }

    public function equals(self $other): bool
    {
        return $this->group->equals($other->group)
            && $this->translation->equals($other->translation)
            && $this->layout->equals($other->layout)
            && $this->layoutId === $other->layoutId
            && $this->status->equals($other->status);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'translation_group_key' => $this->group->value(),
            'page_translation_key' => $this->translation->value(),
            'layout_key' => $this->layout->value(),
            'layout_id' => $this->layoutId,
            'locale' => $this->translation->locale(),
            'status' => $this->status->toArray(),
        ];
    }
}
