<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Community\Discovery;

/**
 * The deterministic sort orders of the discovery layer (Phase 26.50D, Rule 8).
 *
 * Every order is total and deterministic: ties always fall back to name then rid, so
 * the same resource set always yields the same sequence. There is NO popularity or
 * algorithmic ranking — `newest` / `updated` read curated catalog timestamps and
 * `featured` reads the curated featured flag and sort weight (Rule 8).
 *
 * @see docs/discovery-service.md
 */
enum DiscoverySort: string
{
    case Featured = 'featured';
    case Newest = 'newest';
    case Updated = 'updated';
    case Name = 'name';
    case Category = 'category';

    public static function fromString(?string $value): self
    {
        return ($value !== null ? self::tryFrom($value) : null) ?? self::Featured;
    }

    /**
     * A deterministic comparator over {@see CatalogMetadata}. Every branch ends in the
     * shared name/rid tiebreak so the ordering is total.
     *
     * @return callable(CatalogMetadata, CatalogMetadata): int
     */
    public function comparator(): callable
    {
        return match ($this) {
            self::Featured => static function (CatalogMetadata $a, CatalogMetadata $b): int {
                return [$b->featured ? 1 : 0, $b->sortWeight] <=> [$a->featured ? 1 : 0, $a->sortWeight]
                    ?: self::byName($a, $b);
            },
            self::Newest => static fn (CatalogMetadata $a, CatalogMetadata $b): int => ($b->createdAt <=> $a->createdAt) ?: self::byName($a, $b),
            self::Updated => static fn (CatalogMetadata $a, CatalogMetadata $b): int => ($b->updatedAt <=> $a->updatedAt) ?: self::byName($a, $b),
            self::Name => static fn (CatalogMetadata $a, CatalogMetadata $b): int => self::byName($a, $b),
            self::Category => static function (CatalogMetadata $a, CatalogMetadata $b): int {
                return strcasecmp($a->category, $b->category) ?: self::byName($a, $b);
            },
        };
    }

    private static function byName(CatalogMetadata $a, CatalogMetadata $b): int
    {
        return strcasecmp($a->name, $b->name) ?: strcmp($a->rid, $b->rid);
    }
}
