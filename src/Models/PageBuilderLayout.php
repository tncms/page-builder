<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Plugins\PageBuilder\Support\RenderSource;

/**
 * A stored Page Builder layout for a single owner (Phase 2 — Layout Storage).
 *
 * Holds the structured layout tree (sections → blocks) as JSON in two columns —
 * `draft_payload` (work in progress) and `published_payload` (live) — never
 * HTML. `editor_mode` decides whether the owner renders with the builder
 * ("builder") or the classic content ("classic", the default).
 *
 * @property string $owner_type
 * @property int $owner_id
 * @property string $editor_mode
 * @property array<string, mixed>|null $draft_payload
 * @property array<string, mixed>|null $published_payload
 * @property string|null $draft_updated_by
 * @property string|null $published_by
 */
class PageBuilderLayout extends Model
{
    /** Owner renders with the classic content (no builder). */
    public const MODE_CLASSIC = 'classic';

    /** Owner renders with the Page Builder layout. */
    public const MODE_BUILDER = 'builder';

    protected $table = 'pb_layouts';

    protected $fillable = [
        'owner_type',
        'owner_id',
        'editor_mode',
        'draft_payload',
        'published_payload',
        'draft_updated_by',
        'published_by',
    ];

    protected $casts = [
        'draft_payload' => 'array',
        'published_payload' => 'array',
    ];

    protected $attributes = [
        'editor_mode' => self::MODE_CLASSIC,
    ];

    /**
     * The owning content (page / post / any model).
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Whether this owner renders with the Page Builder rather than classic mode.
     */
    public function isPageBuilderMode(): bool
    {
        return $this->editor_mode === self::MODE_BUILDER;
    }

    /**
     * The domain Render Source for this owner (ADR-PB-RENDER-SOURCE-001).
     *
     * The single storage/domain boundary: it maps the legacy `editor_mode` storage
     * value to the {@see RenderSource} concept, failing closed to Classic on an
     * unknown/corrupt value. Consumers should prefer this over raw `editor_mode`
     * string comparisons.
     */
    public function renderSource(): RenderSource
    {
        return RenderSource::fromStorage($this->editor_mode);
    }

    /**
     * Whether a published layout exists (has been published at least once).
     */
    public function hasPublished(): bool
    {
        return $this->published_payload !== null;
    }
}
