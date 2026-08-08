<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Support\DynamicStyle;

/**
 * Validates + canonicalizes raw Builder styling into a safe
 * {@see DynamicStyleDeclaration} (Phase 26.2.1).
 *
 * This is the ONLY trust boundary of the Dynamic Style Runtime. Everything the
 * registry emits into `<head>` has passed through here, so the guarantees below
 * are the whole security model:
 *
 *   1. WHITELIST — a property is dropped unless it is a key of {@see VALIDATORS}.
 *      `position`, `z-index`, `display:none`, `pointer-events`, `behavior`, … are
 *      simply absent, so they can never be emitted.
 *   2. GLOBAL DENY — every value is first checked for CSS-injection primitives
 *      ({@see FORBIDDEN}: `expression(`, `javascript:`, `url(`, `var(`, `calc(`,
 *      `!important`, and structural characters `;{}<>@\` etc.). A hit drops the
 *      declaration outright — no escaping, no sanitising, no "best effort".
 *   3. PER-PROPERTY VALUE VALIDATION — each surviving value must additionally
 *      match its property's own strict validator (a length is only digits+unit,
 *      a colour is only a hex/rgb/hsl/keyword form, an enum is a fixed set).
 *
 * A value that fails ANY step is discarded (the property is omitted); it is
 * never partially accepted. The result is sorted by property so equivalent
 * inputs canonicalize identically ({@see DynamicStyleHasher} relies on this).
 */
final class DynamicStyleNormalizer
{
    /**
     * Substrings / characters that, appearing anywhere in a value, mark it as a
     * CSS-injection attempt and drop the whole declaration. Applied case-insensitively.
     *
     * @var list<string>
     */
    private const FORBIDDEN = [
        'expression', 'javascript:', 'url(', 'var(', 'calc(', '!important',
        '/*', '*/', '\\', '</', '<', '>', '{', '}', ';', '@', '`',
    ];

    /**
     * Hard ceiling on a single value's length. Every supported value (a length,
     * a colour, a keyword, a short shadow) is well under this; a longer value is
     * pathological, so it is dropped before the regex/hash work runs. Bounds the
     * cost of a hostile payload and is a second line under the deny-list.
     */
    private const MAX_VALUE_LENGTH = 256;

    /**
     * Property whitelist. The key is the emitted CSS property; the value is the
     * name of the validator method used for its value. A property outside this
     * map is silently ignored.
     *
     * @var array<string, string>
     */
    private const VALIDATORS = [
        // Box spacing (length or 1-4 lengths).
        'padding' => 'length', 'padding-top' => 'length', 'padding-right' => 'length',
        'padding-bottom' => 'length', 'padding-left' => 'length', 'padding-inline' => 'length',
        'margin' => 'length', 'margin-top' => 'length', 'margin-right' => 'length',
        'margin-bottom' => 'length', 'margin-left' => 'length',
        'gap' => 'length', 'row-gap' => 'length', 'column-gap' => 'length',
        // Sizing.
        'width' => 'length', 'max-width' => 'length', 'min-width' => 'length',
        'height' => 'length', 'max-height' => 'length', 'min-height' => 'length',
        'border-radius' => 'length',
        // Colour.
        'color' => 'color', 'background' => 'color', 'background-color' => 'color',
        // Flexbox alignment (fixed keyword sets).
        'display' => 'display',
        'flex-direction' => 'flexDirection',
        'flex' => 'flex',
        'text-align' => 'textAlign',
        'justify-content' => 'justifyContent',
        'align-items' => 'alignItems',
        // Effects.
        'box-shadow' => 'boxShadow',
    ];

    /**
     * Turn a raw `property => value` map into a validated, canonical declaration.
     * Invalid / unsafe / unsupported entries are dropped. May return empty.
     *
     * @param  array<string, mixed>  $raw
     */
    public function normalize(array $raw): DynamicStyleDeclaration
    {
        $clean = [];

        foreach ($raw as $property => $value) {
            $property = strtolower(trim((string) $property));

            if (! isset(self::VALIDATORS[$property]) || ! is_scalar($value)) {
                continue;
            }

            $value = trim((string) $value);

            if ($value === ''
                || strlen($value) > self::MAX_VALUE_LENGTH
                || $this->hasControlChars($value)
                || $this->isForbidden($value)
                || ! $this->passes(self::VALIDATORS[$property], $value)) {
                continue;
            }

            $clean[$property] = $value;
        }

        ksort($clean);

        return DynamicStyleDeclaration::fromCanonical($clean);
    }

    /**
     * Any C0/C1 control character (newline, carriage return, tab, NUL, …) marks
     * the value as hostile. Without this, an embedded newline could let a value
     * satisfy an anchored `/…$/` validator (PCRE `$` matches before a trailing
     * `\n`) and smuggle a second line into the stylesheet. Denied outright.
     */
    private function hasControlChars(string $value): bool
    {
        return (bool) preg_match('/[\x00-\x1f\x7f]/', $value);
    }

    /** A value containing any CSS-injection primitive is rejected wholesale. */
    private function isForbidden(string $value): bool
    {
        $haystack = strtolower($value);

        foreach (self::FORBIDDEN as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function passes(string $validator, string $value): bool
    {
        return match ($validator) {
            'length' => $this->isLength($value),
            'color' => $this->isColor($value),
            'display' => in_array($value, ['flex', 'grid', 'block', 'inline-block', 'inline-flex', 'inline'], true),
            'flexDirection' => in_array($value, ['row', 'column', 'row-reverse', 'column-reverse'], true),
            'textAlign' => in_array($value, ['left', 'right', 'center', 'justify', 'start', 'end'], true),
            'justifyContent' => in_array($value, ['flex-start', 'flex-end', 'center', 'space-between', 'space-around', 'space-evenly', 'start', 'end', 'stretch'], true),
            'alignItems' => in_array($value, ['flex-start', 'flex-end', 'center', 'stretch', 'baseline', 'start', 'end'], true),
            'flex' => $this->isFlex($value),
            'boxShadow' => $this->isBoxShadow($value),
            default => false,
        };
    }

    /** 1-4 space-separated CSS lengths: digits + `px|rem|em|%` (or a bare 0). */
    private function isLength(string $value): bool
    {
        $tokens = preg_split('/\s+/', $value) ?: [];

        if ($tokens === [] || count($tokens) > 4) {
            return false;
        }

        foreach ($tokens as $token) {
            if (! preg_match('/^-?(?:0|\d+(?:\.\d+)?(?:px|rem|em|%))$/', $token)) {
                return false;
            }
        }

        return true;
    }

    /** Hex, rgb()/rgba(), hsl()/hsla(), or a small keyword set. No url()/var(). */
    private function isColor(string $value): bool
    {
        $lower = strtolower($value);

        if (in_array($lower, ['transparent', 'currentcolor', 'inherit', 'white', 'black', 'red', 'green', 'blue', 'gray', 'grey'], true)) {
            return true;
        }

        return (bool) preg_match('/^#[0-9a-f]{3}$|^#[0-9a-f]{4}$|^#[0-9a-f]{6}$|^#[0-9a-f]{8}$/i', $value)
            || (bool) preg_match('/^rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*(?:,\s*(?:0|1|0?\.\d+)\s*)?\)$/i', $value)
            || (bool) preg_match('/^hsla?\(\s*\d{1,3}\s*,\s*\d{1,3}%\s*,\s*\d{1,3}%\s*(?:,\s*(?:0|1|0?\.\d+)\s*)?\)$/i', $value);
    }

    /** The width basis shorthand only: `<grow> <shrink> <basis-length>`, e.g. `0 0 30%`. */
    private function isFlex(string $value): bool
    {
        return (bool) preg_match('/^[01]\s+[01]\s+(?:0|\d+(?:\.\d+)?(?:px|rem|em|%))$/', $value);
    }

    /** Constrained shadow: optional `inset`, 2-4 lengths, then one hex/rgb colour. */
    private function isBoxShadow(string $value): bool
    {
        if (! preg_match('/^(?:inset\s+)?((?:-?\d+(?:\.\d+)?(?:px|rem)\s+){2,4})(.+)$/i', $value, $m)) {
            return false;
        }

        return $this->isColor(trim($m[2]));
    }
}
