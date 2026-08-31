<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Support;

/**
 * Renders the Phase 19 box-model spacing (`data.spacing`) into an inline style on
 * a component's root element.
 *
 * The Box Model overlay commits an editor-owned, flat `spacing` map:
 *
 *   'spacing' => [
 *       'padding' => ['top' => 10, 'right' => 0, 'bottom' => 10, 'left' => 0],
 *       'margin'  => ['top' => 0,  'right' => 0, 'bottom' => 20, 'left' => 0],
 *   ]
 *
 * This is deliberately NOT a schema field on any definition (so existing schema
 * tests are untouched) and is purely additive: a component with no `spacing`
 * data (i.e. every existing one) produces an empty string and is rendered
 * byte-for-byte as before. Values are sanitised to a CSS length, so nothing a
 * user types can inject arbitrary CSS.
 */
final class SpacingStyle
{
    private const SIDES = ['top', 'right', 'bottom', 'left'];

    /**
     * Build the inline CSS declarations for a component's `spacing` data
     * (empty string when there is none).
     */
    public static function css(mixed $spacing): string
    {
        if (! is_array($spacing)) {
            return '';
        }

        return trim(
            self::box('padding', $spacing['padding'] ?? null)
            .self::box('margin', $spacing['margin'] ?? null)
        );
    }

    /**
     * Inject a style string into the first opening tag of an HTML fragment,
     * merging with an existing `style="…"` when present. Returns the HTML
     * unchanged when the style is empty or no opening tag is found.
     */
    public static function inject(string $html, string $style): string
    {
        if ($style === '') {
            return $html;
        }

        if (! preg_match('/<[a-zA-Z][a-zA-Z0-9]*\b/', $html, $m, PREG_OFFSET_CAPTURE)) {
            return $html;
        }

        $tagStart = (int) $m[0][1];
        $gt = strpos($html, '>', $tagStart);
        if ($gt === false) {
            return $html;
        }

        $openTag = substr($html, $tagStart, $gt - $tagStart);

        if (preg_match('/\sstyle\s*=\s*"/i', $openTag)) {
            $newOpen = preg_replace('/(\sstyle\s*=\s*")/i', '$1'.$style.';', $openTag, 1);
        } else {
            $selfClosing = str_ends_with(rtrim($openTag), '/');
            $newOpen = rtrim(rtrim($openTag), '/').' style="'.$style.'"'.($selfClosing ? ' /' : '');
        }

        return substr($html, 0, $tagStart).$newOpen.substr($html, $gt);
    }

    /**
     * Convenience: compute + inject spacing for a component's data map.
     */
    public static function apply(string $html, mixed $data): string
    {
        $spacing = is_array($data) ? ($data['spacing'] ?? null) : null;

        return self::inject($html, self::css($spacing));
    }

    /**
     * The sanitised `property => value` map for a `{padding, margin}` spacing map —
     * the same declarations {@see css()} emits inline, but shaped for the runtime
     * style registry (PB-PROD-1). Empty when there is nothing safe to emit.
     *
     * @return array<string, string>
     */
    public static function declarations(mixed $spacing): array
    {
        if (! is_array($spacing)) {
            return [];
        }

        return self::boxDeclarations('padding', $spacing['padding'] ?? null)
            + self::boxDeclarations('margin', $spacing['margin'] ?? null);
    }

    /**
     * @return array<string, string>
     */
    private static function boxDeclarations(string $prop, mixed $sides): array
    {
        if (! is_array($sides)) {
            return [];
        }

        $decls = [];
        foreach (self::SIDES as $side) {
            $length = self::length($sides[$side] ?? null);
            if ($length !== null) {
                $decls[$prop.'-'.$side] = $length;
            }
        }

        return $decls;
    }

    private static function box(string $prop, mixed $sides): string
    {
        if (! is_array($sides)) {
            return '';
        }

        $css = '';
        foreach (self::SIDES as $side) {
            $length = self::length($sides[$side] ?? null);
            if ($length !== null) {
                $css .= $prop.'-'.$side.':'.$length.';';
            }
        }

        return $css;
    }

    /**
     * Sanitise a side value into a safe CSS length, or null to skip it.
     * Bare numbers become px; strings are accepted only if they are a plain
     * number + a known unit.
     */
    private static function length(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return ((float) $value === floor((float) $value) ? (string) (int) $value : (string) $value).'px';
        }

        if (is_string($value) && preg_match('/^-?\d+(\.\d+)?(px|rem|em|%|vw|vh)?$/', trim($value), $m)) {
            $v = trim($value);

            return isset($m[2]) && $m[2] !== '' ? $v : $v.'px';
        }

        return null;
    }
}
