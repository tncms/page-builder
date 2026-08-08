<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Identity;

use InvalidArgumentException;

/**
 * The identity of a Translation Group — LT-1 (locked).
 *
 * A Translation Group is the set of all locale variants of one content item. Its
 * identity is CONSUMED from the Core-owned entity identity (the TN CMS Content's
 * (type, id)), which is Core's cross-locale anchor for pages/posts; the plugin
 * does not invent a group id space and never derives identity from a locale,
 * slug, title or table name.
 *
 * Core-gap note: for core Pages/Posts (the parent + child cms_content_translations
 * model) Core exposes no dedicated @stable typed translation-group identity value
 * object — the anchor is surfaced only as the raw Content (type, id) / the
 * Experimental {@see \TheNguyen\CMS\Translation\Entity\Contracts\LocalizedEntityInterface}
 * (type, key) / {@see \TheNguyen\CMS\Contracts\TranslatablePluginContent::translationGroup()}
 * for the other model. This VO is a plugin-side projection of that Core identity;
 * if Core later exposes a unified translation-group identity contract it is
 * adopted additively. Fail closed: a key is only built from a resolved Core owner.
 */
final class TranslationGroupKey
{
    public const NAMESPACE = 'translation-group';

    private function __construct(
        public readonly string $ownerType,
        public readonly string $ownerId,
    ) {}

    /**
     * Project the Core owner (Content) identity into a group key.
     */
    public static function forOwner(string $ownerType, int|string $ownerId): self
    {
        $type = trim($ownerType);
        $id = trim((string) $ownerId);

        if ($type === '' || $id === '') {
            throw new InvalidArgumentException('A translation group key requires a non-empty owner type and id.');
        }

        return new self($type, $id);
    }

    /** The canonical, stable, presentation-independent representation. */
    public function value(): string
    {
        return self::NAMESPACE.':'.$this->ownerType.':'.$this->ownerId;
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
            'owner_type' => $this->ownerType,
            'owner_id' => $this->ownerId,
            'value' => $this->value(),
        ];
    }
}
