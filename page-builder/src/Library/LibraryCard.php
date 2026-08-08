<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Library;

/**
 * Immutable view-model for one Design Library card.
 *
 * Carries only display metadata plus a preview URL (which points at the Runtime
 * Preview Engine). It deliberately exposes NO filesystem paths or package-internal
 * layout — the Design Library UI never touches storage directly.
 */
final class LibraryCard
{
    /**
     * @param  array<int, string>  $tags
     */
    public function __construct(
        public readonly string $rid,
        public readonly string $name,
        public readonly string $kind,
        public readonly string $vendor,
        public readonly string $version,
        public readonly string $category,
        public readonly array $tags,
        public readonly string $previewUrl,
        public readonly string $entitlement,
        public readonly bool $available = true,
        public readonly bool $favorite = false,
        public readonly ?string $description = null,
        public readonly bool $locked = false,
        public readonly ?string $requiredCapability = null,
        public readonly int $sectionCount = 0,
    ) {
    }

    public function withFavorite(bool $favorite): self
    {
        return new self(
            $this->rid, $this->name, $this->kind, $this->vendor, $this->version,
            $this->category, $this->tags, $this->previewUrl, $this->entitlement,
            $this->available, $favorite, $this->description,
            $this->locked, $this->requiredCapability, $this->sectionCount,
        );
    }

    /**
     * Whether the current actor may import/insert this resource. A locked resource
     * stays listed and previewable — only this action is gated.
     */
    public function isImportable(): bool
    {
        return $this->available && ! $this->locked;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'rid' => $this->rid,
            'name' => $this->name,
            'kind' => $this->kind,
            'vendor' => $this->vendor,
            'version' => $this->version,
            'category' => $this->category,
            'tags' => $this->tags,
            'previewUrl' => $this->previewUrl,
            'entitlement' => $this->entitlement,
            'available' => $this->available,
            'favorite' => $this->favorite,
            'description' => $this->description,
            'locked' => $this->locked,
            'requiredCapability' => $this->requiredCapability,
            'sectionCount' => $this->sectionCount,
        ];
    }
}
