<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Support;

/**
 * Bounded semantic accent presets (PB-DEMO-DESIGN-R1-A).
 *
 * The single server-side authority for the accent contract. A section opts into a
 * cohesive accent by storing one of {@see ACCENTS} in its `data.accent`; the
 * runtime emits ONLY the fixed, plugin-owned classes `pb-accent pb-accent--{name}`.
 * Those classes map to semantic `--pb-accent-*` custom properties and a small set
 * of SCOPED overrides in the frontend stylesheet, so the accent recolours
 * interactive controls + eyebrows WITHIN that section subtree only — never Core,
 * the theme chrome, another plugin, or another template.
 *
 * It is a NAMED preset, never an arbitrary colour: any unknown/empty/malformed
 * value fails closed to "" (no class), so raw hex/rgb/`var(...)`/URL/CSS can never
 * reach the stylesheet through this seam. The whitelist is validated here
 * (server-side) and mirrored by the bounded editor `SelectField` options
 * (client-side).
 */
final class AccentPreset
{
    /** The whitelisted accent names (a small, curated design-system palette). */
    public const ACCENTS = ['cobalt', 'ember', 'forest', 'plum', 'slate'];

    /** The normalized accent name, or null when the value is not whitelisted. */
    public static function normalize(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $name = strtolower(trim($value));

        return in_array($name, self::ACCENTS, true) ? $name : null;
    }

    /**
     * The fail-closed class attribute fragment for a stored accent value:
     * `"pb-accent pb-accent--{name}"` for a whitelisted name, otherwise `""`.
     */
    public static function classes(mixed $value): string
    {
        $name = self::normalize($value);

        return $name === null ? '' : 'pb-accent pb-accent--'.$name;
    }

    /**
     * The bounded editor `SelectField` option map: a leading "None" (empty) plus the
     * whitelisted accents, so the inspector can only offer safe, known values.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = ['' => 'None'];
        foreach (self::ACCENTS as $name) {
            $options[$name] = ucfirst($name);
        }

        return $options;
    }
}
