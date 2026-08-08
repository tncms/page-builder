<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Identity;

/**
 * The identity of a Page Builder Layout — LT-1 (locked).
 *
 * A layout is the plugin-owned implementation detail of exactly one
 * {@see PageTranslationKey}. Its identity is therefore derived from the page
 * translation it implements — a single source of identity truth — and is
 * presentation- and storage-independent (never a slug, title or table name).
 */
final class LayoutKey
{
    public const NAMESPACE = 'layout';

    private function __construct(public readonly PageTranslationKey $translation) {}

    public static function forTranslation(PageTranslationKey $translation): self
    {
        return new self($translation);
    }

    public function translation(): PageTranslationKey
    {
        return $this->translation;
    }

    public function value(): string
    {
        return self::NAMESPACE.':'.$this->translation->group->ownerType
            .':'.$this->translation->group->ownerId
            .':'.$this->translation->locale;
    }

    public function equals(self $other): bool
    {
        return $this->value() === $other->value();
    }

    public function __toString(): string
    {
        return $this->value();
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'namespace' => self::NAMESPACE,
            'translation' => $this->translation->value(),
            'value' => $this->value(),
        ];
    }
}
