<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Support;

/**
 * Bounded semantic section-tone presets (PB-DEMO-DESIGN-R1-A-R2).
 *
 * The single server-side authority for the body-rhythm contract: a section opts
 * into a tonal surface by storing one of {@see TONES} in its `data.tone`; the
 * runtime emits ONLY the fixed, plugin-owned classes `pb-tone pb-tone--{name}`.
 * Those classes map to a small set of semantic surfaces in the frontend stylesheet
 * (warm neutral, soft accent tint, dark ink, strong accent) with WCAG-AA text /
 * link / focus / button contrast, SCOPED to that Page Builder section subtree — the
 * tone never touches Core, the theme chrome, another plugin, or another template.
 *
 * It is a NAMED preset, never an arbitrary colour: any unknown/empty/malformed
 * value fails closed to "" (no class), so raw hex/rgb/`var(...)`/URL/CSS can never
 * reach the stylesheet through this seam. The whitelist is validated here
 * (server-side) and mirrored by the bounded editor `SelectField` options.
 */
final class TonePreset
{
    /** The whitelisted tone names. */
    public const TONES = ['neutral', 'tint', 'ink', 'accent'];

    /** The normalized tone name, or null when the value is not whitelisted. */
    public static function normalize(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $name = strtolower(trim($value));

        return in_array($name, self::TONES, true) ? $name : null;
    }

    /**
     * The fail-closed class attribute fragment: `"pb-tone pb-tone--{name}"` for a
     * whitelisted tone, otherwise `""`.
     */
    public static function classes(mixed $value): string
    {
        $name = self::normalize($value);

        return $name === null ? '' : 'pb-tone pb-tone--'.$name;
    }

    /** The tone classes prefixed with a leading space, or "" — for inline append. */
    public static function spacedClass(mixed $value): string
    {
        $classes = self::classes($value);

        return $classes === '' ? '' : ' '.$classes;
    }

    /**
     * The bounded editor option map: a leading "None" (base/white) plus the
     * whitelisted tones.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = ['' => 'Base (white)'];
        foreach (self::TONES as $name) {
            $options[$name] = ucfirst($name);
        }

        return $options;
    }
}
