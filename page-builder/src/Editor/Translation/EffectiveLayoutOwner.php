<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Editor\Translation;

use Plugins\PageBuilder\LayoutTranslation\Identity\PageTranslationKey;
use Plugins\PageBuilder\LayoutTranslation\Identity\TranslationGroupKey;

/**
 * The server-resolved effective layout owner for one editor request (LT-M1B).
 *
 * Immutable. Separates the *canonical* owner the client addresses (always a
 * supported page/post Content) from the *effective* `pb_layouts` owner the
 * layout is actually stored under:
 *
 *   - default (source) locale → the canonical owner itself (`page`, page.id);
 *   - non-default locale       → (`content_translation`, ContentTranslation.id).
 *
 * The client never supplies the effective owner; it is derived here from the
 * canonical page + requested locale through the frozen LT resolver + owner model.
 */
final class EffectiveLayoutOwner
{
    public function __construct(
        public readonly string $canonicalOwnerType,
        public readonly int|string $canonicalOwnerId,
        public readonly string $ownerType,
        public readonly int|string $ownerId,
        public readonly ?string $requestedLocale,
        public readonly string $locale,
        public readonly string $sourceLocale,
        public readonly bool $isSourceLocale,
        public readonly ?int $contentTranslationId,
        public readonly PageTranslationKey $translation,
        public readonly TranslationGroupKey $group,
    ) {}

    public function isTranslationOwner(): bool
    {
        return ! $this->isSourceLocale;
    }

    /**
     * Read-only diagnostics for the editor boot payload. Never authoritative on
     * the client — the server re-resolves the owner on every mutation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'requested_locale' => $this->requestedLocale,
            'resolved_locale' => $this->locale,
            'source_locale' => $this->sourceLocale,
            'is_source' => $this->isSourceLocale,
            'canonical_owner_type' => $this->canonicalOwnerType,
            'canonical_owner_id' => $this->canonicalOwnerId,
            'layout_owner_type' => $this->ownerType,
            'layout_owner_id' => $this->ownerId,
            'content_translation_id' => $this->contentTranslationId,
        ];
    }
}
