<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Support;

/**
 * The public Render Source for an effective layout owner (ADR-PB-RENDER-SOURCE-001).
 *
 * This is the DOMAIN authority for "which content source the public frontend
 * renders" — distinct from Editor Mode ("how content is currently edited"). The
 * two currently share the legacy `pb_layouts.editor_mode` storage column, but
 * this enum is the concept; storage does not define it.
 *
 * Exactly two values, no Auto / Draft / Published / Preview / Hybrid /
 * Translation value. The backed values equal the current `editor_mode` strings so
 * the storage mapping is identity at the boundary (no data change). Unknown or
 * corrupt storage fails closed to {@see self::Classic}. This is a pure value
 * type: no persistence, rendering, container, or service-locator logic.
 */
enum RenderSource: string
{
    /** The public page renders Core-owned Classic content. */
    case Classic = 'classic';

    /** The public page renders the effective owner's published Page Builder layout. */
    case Builder = 'builder';

    /**
     * Map from the legacy `editor_mode` storage value. Unknown / null fails
     * closed to Classic.
     */
    public static function fromStorage(?string $value): self
    {
        return $value === self::Builder->value ? self::Builder : self::Classic;
    }

    /**
     * The current storage representation for this source (the `editor_mode` value).
     */
    public function toStorage(): string
    {
        return $this->value;
    }
}
