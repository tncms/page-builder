<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Community\Publishing;

/**
 * The official Community Resource lifecycle status (Phase 26.50B, Rules 1 & 2).
 *
 * A resource moves strictly through:
 *   Draft → Validated → Packaged → Published → Deprecated → Archived
 * (with restore from Archived). There are NO other active states.
 *
 * `rejected`, `suspended` and `deleted` are RESERVED for a future moderation /
 * authorization layer (Marketplace / Enterprise) — they are NOT implemented here
 * (Rule 2) and are exposed only as reserved names so the vocabulary is fixed.
 *
 * @see docs/resource-lifecycle.md
 */
enum ResourceStatus: string
{
    case Draft = 'draft';
    case Validated = 'validated';
    case Packaged = 'packaged';
    case Published = 'published';
    case Deprecated = 'deprecated';
    case Archived = 'archived';

    /** Reserved statuses — declared, never implemented in this phase (Rule 2). */
    public const RESERVED = ['rejected', 'suspended', 'deleted'];

    public static function fromString(string $value): self
    {
        return self::from($value);
    }

    public static function tryFromString(?string $value): ?self
    {
        return $value === null ? null : self::tryFrom($value);
    }

    /**
     * Whether the Community Provider exposes a resource in this status. Published and
     * Deprecated resources are visible (deprecated ones still searchable/installable
     * with a warning — Rule 9); everything else stays local/hidden (Rules 10, 13).
     */
    public function isExposed(): bool
    {
        return $this === self::Published || $this === self::Deprecated;
    }

    public function isPublished(): bool
    {
        return $this === self::Published;
    }

    public function isDeprecated(): bool
    {
        return $this === self::Deprecated;
    }

    public function isArchived(): bool
    {
        return $this === self::Archived;
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
