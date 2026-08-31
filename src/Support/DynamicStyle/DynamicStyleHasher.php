<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Support\DynamicStyle;

/**
 * Deterministic, order-independent hash of a {@see DynamicStyleDeclaration}
 * (Phase 26.2.1).
 *
 * The declaration is already sorted by property, so `padding:24px;background:#fff`
 * and `background:#fff;padding:24px` reduce to the SAME canonical string and thus
 * the SAME hash — equivalent styling deduplicates to one class. The hash is a
 * short hex slice of sha1 (stable across requests/processes, no randomness), safe
 * as a CSS class suffix.
 */
final class DynamicStyleHasher
{
    /** Hash length (hex chars). 8 keeps classes short while making collisions negligible. */
    private const LENGTH = 8;

    /**
     * Hash the declaration, optionally namespaced by a `@media` condition so the
     * SAME declarations under different breakpoints hash to distinct classes
     * (PB-PROD-1). When `$media` is null the hash is unchanged, so every existing
     * (non-responsive) class stays byte-identical.
     */
    public function hash(DynamicStyleDeclaration $declaration, ?string $media = null): string
    {
        $input = ($media !== null ? $media.'|' : '').$declaration->toCss();

        return substr(sha1($input), 0, self::LENGTH);
    }
}
