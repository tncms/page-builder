<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Community\Discovery;

/**
 * A curated **resource collection** descriptor (Phase 26.50D, Rule 4).
 *
 * A collection is a named grouping for discovery — "Starter Designs", "Landing Pages",
 * "SaaS Templates" and so on. It is a display descriptor only: membership is additive
 * metadata carried per-resource by {@see CatalogMetadata::$collections} (a resource
 * belongs to a collection when its catalog metadata lists the collection key), so a
 * collection never stores payload and never becomes another repository (Rule 1).
 *
 * @see docs/collections.md
 */
final class Collection
{
    public function __construct(
        public readonly string $key,
        public readonly string $title,
        public readonly ?string $description = null,
        public readonly ?string $kind = null,
        public readonly ?string $coverRid = null,
        public readonly int $sortWeight = 0,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            key: (string) ($data['key'] ?? ''),
            title: (string) ($data['title'] ?? ($data['key'] ?? '')),
            description: isset($data['description']) ? (string) $data['description'] : null,
            kind: isset($data['kind']) && $data['kind'] !== '' ? (string) $data['kind'] : null,
            coverRid: isset($data['cover_rid']) && $data['cover_rid'] !== '' ? (string) $data['cover_rid'] : null,
            sortWeight: (int) ($data['sort_weight'] ?? 0),
        );
    }

    public function isValid(): bool
    {
        return $this->key !== '' && $this->title !== '';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'title' => $this->title,
            'description' => $this->description,
            'kind' => $this->kind,
            'cover_rid' => $this->coverRid,
            'sort_weight' => $this->sortWeight,
        ];
    }
}
