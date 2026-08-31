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

    /**
     * The server-issued optimistic-concurrency token for this owner's authoritative
     * layout state (PB-PUBLISH-R1, INV-PUB-06 — Version Safety).
     *
     * A content hash over a CANONICAL (recursively key-sorted) serialization of the
     * persisted state — draft + published payloads and render mode. It is derived from
     * persisted content only, never object identity, timestamps, client state, OR the
     * object-key order a store happened to persist (see {@see self::tokenFor}), so two
     * rows with identical content yield the same token, a repeat publish with no change
     * is idempotent, and the token a save issues equals the token the next save's CAS
     * recomputes even after the row round-trips a normalizing JSON column. Save Draft /
     * Publish compare the caller's token against this inside a locked transaction.
     */
    public function versionToken(): string
    {
        return self::tokenFor($this->draft_payload, $this->published_payload, $this->editor_mode);
    }

    /**
     * The token for an owner that has no stored layout row yet (first open) — the
     * baseline both the client and the server start from before the first write.
     */
    public static function emptyToken(): string
    {
        return self::tokenFor(null, null, self::MODE_CLASSIC);
    }

    /**
     * @param  array<string, mixed>|null  $draft
     * @param  array<string, mixed>|null  $published
     */
    private static function tokenFor(?array $draft, ?array $published, ?string $mode): string
    {
        // PB-PUBLISH-R1-H1 (Defect 1) — hash a CANONICAL (recursively key-sorted)
        // serialization so the token depends only on content, never on object-key
        // ORDER. This is required because the token issued by a save (computed from the
        // just-written, un-reloaded model in PHP insertion order) is compared on the
        // NEXT save against the same row re-read from storage — and MySQL's native JSON
        // type normalizes (reorders) object keys on write. Without canonicalization the
        // two orderings hash differently, so a single editor's second consecutive save
        // 409s against a token the server itself just issued (Save #1 ok, Save #2
        // conflict). Sorting keys makes both orderings collapse to one token; list
        // (0..n) order is preserved because ksort keeps already-ordered numeric keys.
        return hash('sha256', (string) json_encode([
            'draft' => self::canonicalize($draft),
            'published' => self::canonicalize($published),
            'mode' => $mode ?? self::MODE_CLASSIC,
        ], JSON_UNESCAPED_UNICODE));
    }

    /**
     * Recursively sort array keys so a structure hashes identically regardless of the
     * object-key order a store (e.g. MySQL JSON) chose to persist it in. Scalars and
     * null pass through untouched; sequential lists keep their meaningful order.
     */
    private static function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $canonical = array_map([self::class, 'canonicalize'], $value);
        ksort($canonical);

        return $canonical;
    }
}
