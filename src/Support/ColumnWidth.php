<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Support;

/**
 * Single source of truth for a Column's width (Phase 25.3.1; arbitrary percentages
 * + safety added 25.3.2 §6).
 *
 * A stored `width` may be:
 *   - a canonical percentage token: "30", "50", "33.3333"
 *   - an explicit percentage string: "30%", "50%", "33.3333%"
 *   - a raw number: 30, 50 (int/float)
 *   - a legacy semantic alias: full / half / third / quarter
 *   - auto (or empty / unknown)
 *
 * Everything resolves to ONE CSS flex-basis (e.g. "50%") so the column blade, the
 * editor marker (ComponentRuntime) and every test agree exactly. Unsafe input
 * (units other than %, calc(), CSS injection) is rejected → null, so nothing a
 * user types can leak arbitrary CSS into an inline style.
 *
 * `auto` (and anything unknown/unsafe) has no fixed basis: the column flexes to
 * fill the remaining space, matching the pre-composition default.
 */
final class ColumnWidth
{
    /** Legacy semantic aliases → canonical percentage number. */
    private const ALIASES = [
        'full' => '100',
        'half' => '50',
        'third' => '33.3333',
        'quarter' => '25',
    ];

    /**
     * The CSS flex-basis for a width, resolving legacy aliases too — used by the
     * editor marker so a legacy alias column is still laid out correctly.
     */
    public static function basis(mixed $width): ?string
    {
        return self::resolve($width, resolveAliases: true);
    }

    /**
     * The CSS basis for a width WITHOUT resolving legacy aliases: an explicit
     * percentage / number resolves, but half/third/quarter/auto return null.
     *
     * The public column blade uses this so legacy alias columns keep their
     * byte-identical, class-only output (Phase 25.3 contract) while composition
     * columns get a real inline flex-basis. The editor marker uses {@see basis()}.
     */
    public static function explicitBasis(mixed $width): ?string
    {
        return self::resolve($width, resolveAliases: false);
    }

    /**
     * Normalise a stored width to its canonical token ('50', '33.3333', 'auto').
     * 'auto' when there is no safe fixed basis.
     */
    public static function token(mixed $width): string
    {
        $basis = self::basis($width);

        return $basis === null ? 'auto' : rtrim($basis, '%');
    }

    /**
     * Resolve a width value to a safe "NN%" basis, or null.
     */
    private static function resolve(mixed $width, bool $resolveAliases): ?string
    {
        if (is_int($width) || is_float($width)) {
            return self::percent((float) $width);
        }

        if (! is_string($width)) {
            return null;
        }

        $value = strtolower(trim($width));
        if ($value === '' || $value === 'auto') {
            return null;
        }

        if ($resolveAliases && isset(self::ALIASES[$value])) {
            $value = self::ALIASES[$value];
        }

        if (str_ends_with($value, '%')) {
            $value = substr($value, 0, -1);
        }

        // Only a bare, non-negative decimal is safe to echo into a CSS length.
        // Rejects "50px", "calc(50%)", "100vw", and any injection attempt.
        if (! preg_match('/^\d+(\.\d+)?$/', $value)) {
            return null;
        }

        return self::percent((float) $value);
    }

    /**
     * Clamp + format a numeric percentage (0 < n <= 100) as "NN%", trimming
     * trailing zeros (50.0000 → 50, 33.3333 → 33.3333).
     */
    private static function percent(float $n): ?string
    {
        if ($n <= 0.0 || $n > 100.0) {
            return null;
        }

        $formatted = rtrim(rtrim(number_format($n, 4, '.', ''), '0'), '.');

        return $formatted.'%';
    }
}
