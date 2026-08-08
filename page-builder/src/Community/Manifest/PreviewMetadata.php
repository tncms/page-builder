<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Community\Manifest;

/**
 * Preview metadata in a Resource Manifest (Phase 26.50A, Rules 2 & 4).
 *
 * The manifest records only preview METADATA — an optional static thumbnail and an
 * optional preview locator. The authoritative, live preview is still produced
 * exclusively by the one Runtime Preview engine keyed by RID; the manifest never
 * carries a rendered document or a Builder runtime. All fields are platform-neutral
 * and optional so the format works in an offline bundle unchanged.
 */
final class PreviewMetadata
{
    public function __construct(
        public readonly ?string $thumbnail = null,
        public readonly ?string $previewUrl = null,
        public readonly ?int $width = null,
        public readonly ?int $height = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            thumbnail: is_string($data['thumbnail'] ?? null) ? $data['thumbnail'] : null,
            previewUrl: is_string($data['preview_url'] ?? null) ? $data['preview_url'] : null,
            width: is_int($data['width'] ?? null) ? $data['width'] : null,
            height: is_int($data['height'] ?? null) ? $data['height'] : null,
        );
    }

    public function isEmpty(): bool
    {
        return $this->thumbnail === null && $this->previewUrl === null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'thumbnail' => $this->thumbnail,
            'preview_url' => $this->previewUrl,
            'width' => $this->width,
            'height' => $this->height,
        ];
    }
}
