<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Identity;

use InvalidArgumentException;

/**
 * The identity of a Page Translation — LT-1 (locked).
 *
 * A Page Translation is one locale variant within a {@see TranslationGroupKey}.
 * Its identity is the group plus the Core-normalized canonical locale code — never
 * a raw/presentation locale string, slug, title or table name. The group
 * component is itself locale-independent, so the group identity spans all locales
 * while the page-translation identity distinguishes one.
 */
final class PageTranslationKey
{
    public const NAMESPACE = 'page-translation';

    private function __construct(
        public readonly TranslationGroupKey $group,
        public readonly string $locale,
    ) {}

    /**
     * @param  string  $normalizedLocale  a Core-normalized canonical locale code
     */
    public static function forGroup(TranslationGroupKey $group, string $normalizedLocale): self
    {
        $locale = trim($normalizedLocale);

        if ($locale === '') {
            throw new InvalidArgumentException('A page translation key requires a normalized locale.');
        }

        return new self($group, $locale);
    }

    public function group(): TranslationGroupKey
    {
        return $this->group;
    }

    public function locale(): string
    {
        return $this->locale;
    }

    public function value(): string
    {
        return self::NAMESPACE.':'.$this->group->ownerType.':'.$this->group->ownerId.':'.$this->locale;
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
            'group' => $this->group->value(),
            'locale' => $this->locale,
            'value' => $this->value(),
        ];
    }
}
