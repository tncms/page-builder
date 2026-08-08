<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Community\Manifest;

/**
 * A referenced asset in a Resource Manifest (Phase 26.50A, Rule 8).
 *
 * A resource DECLARES the assets it references — images, fonts, icons, media. This
 * is METADATA ONLY: nothing is downloaded, fetched, rehosted or bundled here. A
 * `ref` is an opaque, platform-neutral locator (a relative path in an offline
 * bundle, or a URL) recorded verbatim so the declaration remains usable offline and
 * in an enterprise mirror without changes (Rule 14).
 */
final class AssetReference
{
    public const TYPE_IMAGE = 'image';

    public const TYPE_FONT = 'font';

    public const TYPE_ICON = 'icon';

    public const TYPE_MEDIA = 'media';

    public const TYPE_OTHER = 'other';

    public const TYPES = [self::TYPE_IMAGE, self::TYPE_FONT, self::TYPE_ICON, self::TYPE_MEDIA, self::TYPE_OTHER];

    public function __construct(
        public readonly string $type,
        public readonly string $ref,
        public readonly ?string $role = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            type: is_string($data['type'] ?? null) ? $data['type'] : self::TYPE_OTHER,
            ref: is_string($data['ref'] ?? null) ? $data['ref'] : '',
            role: is_string($data['role'] ?? null) ? $data['role'] : null,
        );
    }

    public function isKnownType(): bool
    {
        return in_array($this->type, self::TYPES, true);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'ref' => $this->ref,
            'role' => $this->role,
        ];
    }
}
