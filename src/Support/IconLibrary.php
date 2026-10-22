<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Support;

/**
 * Bounded, plugin-owned public icon registry (PB-DEMO-DESIGN-R1-A-R2).
 *
 * The single server-side authority for the icon contract consumed by the public
 * renderer AND the editor. Icons are a small whitelist of SEMANTIC names mapped to
 * ORIGINAL, deterministic inline 24×24 stroke SVG geometry — no external font,
 * script, URL, dependency, `foreignObject`, remote reference, event handler, or
 * embedded raster. An unknown/empty/malformed name fails closed to null, so no
 * arbitrary SVG/markup from a payload can ever reach the rendered page through this
 * seam. Editor options are generated from {@see options()}, the same whitelist, so
 * the inspector can only offer safe, known values.
 *
 * {@see paths()} returns ONLY the inner geometry; the owning view wraps it in a
 * single `<svg>` and decides the accessible semantics (decorative `aria-hidden`, or
 * `role="img"` + label), so screen-reader exposure is owned once by the component.
 */
final class IconLibrary
{
    /**
     * name => inner SVG geometry (drawn with the wrapper's `stroke="currentColor"`,
     * `fill="none"`, round caps/joins). Original, simple, geometric.
     *
     * @var array<string, string>
     */
    private const ICONS = [
        // Strategy / discovery — a target with crosshair.
        'strategy' => '<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="3.2"/><path d="M12 2.4v3.2M12 18.4v3.2M2.4 12h3.2M18.4 12h3.2"/>',
        // Brand / identity — a faceted gem.
        'brand' => '<path d="M7 3.5h10l3.5 5.5-8.5 11.5L3.5 9Z"/><path d="M3.5 9h17M9 3.5 7.6 9l4.4 11.5M15 3.5 16.4 9 12 20.5"/>',
        // Product / interface — an application panel.
        'product' => '<rect x="3.5" y="4.5" width="17" height="15" rx="2"/><path d="M3.5 9h17"/><path d="M6.6 6.8h.01M9 6.8h.01"/><path d="M7 12.5h5.5M7 15.5h8"/>',
        // Web / campaign — a globe.
        'web' => '<circle cx="12" cy="12" r="8.5"/><path d="M3.5 12h17"/><path d="M12 3.5c2.4 2.3 3.7 5.3 3.7 8.5S14.4 18.2 12 20.5C9.6 18.2 8.3 15.2 8.3 12S9.6 5.8 12 3.5Z"/>',
        // Build / development — code brackets with a slash.
        'build' => '<path d="M8.5 7 4 12l4.5 5M15.5 7 20 12l-4.5 5M13.6 5l-3.2 14"/>',
        // Launch / growth — a rising trend arrow.
        'launch' => '<path d="M3.5 16.5 9 11l3.5 3.5 8-8.5"/><path d="M15.5 6h5v5"/>',
        // Collaboration — two people.
        'collaboration' => '<circle cx="8.6" cy="8.8" r="3"/><circle cx="16.2" cy="10" r="2.4"/><path d="M3.6 19c.5-3 2.6-4.6 5-4.6s4.5 1.6 5 4.6"/><path d="M14.8 14.3c2-.2 3.8 1.1 4.3 3.4"/>',
        // Measurable outcome — ascending bars.
        'outcome' => '<path d="M4 20h16"/><path d="M6.4 20v-6.5M12 20V9.5M17.6 20V5.5"/>',
    ];

    /** @return list<string> the whitelisted semantic icon names. */
    public static function names(): array
    {
        return array_keys(self::ICONS);
    }

    public static function has(string $name): bool
    {
        return array_key_exists($name, self::ICONS);
    }

    /** The inner SVG geometry for a whitelisted name, or null (fail-closed). */
    public static function paths(mixed $name): ?string
    {
        if (! is_string($name)) {
            return null;
        }

        return self::ICONS[strtolower(trim($name))] ?? null;
    }

    /**
     * The canonical CSS `url("data:image/svg+xml,…")` for a whitelisted icon, built
     * from {@see paths()} — the single geometry source. It is the SANITIZER-PROOF
     * delivery: the public content sanitizer (`cms_html()`) strips inline `<svg>`,
     * but a stylesheet rule keyed by the surviving `.pb-icon--{name}` class is never
     * sanitized, so the geometry reaches the public page through a CSS mask. The
     * white, opaque stroke makes the icon's silhouette the mask (alpha AND luminance),
     * which `.pb-icon`'s `background-color: currentColor` then paints — so the icon
     * adopts the band/badge foreground tone. Fail-closed (null) for unknown names.
     */
    public static function maskUri(mixed $name): ?string
    {
        $paths = self::paths($name);
        if ($paths === null) {
            return null;
        }

        $svg = "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='#fff' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'>".$paths.'</svg>';
        // URL-encode only the characters that would break a CSS url("data:…"): the
        // angle brackets, the hash, and the attribute double-quotes.
        $encoded = strtr($svg, ['<' => '%3C', '>' => '%3E', '#' => '%23', '"' => '%22']);

        return 'url("data:image/svg+xml,'.$encoded.'")';
    }

    /**
     * The bounded editor option map (leading "None" + whitelist), so the inspector
     * can only offer safe, known icon names.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = ['' => 'None'];
        foreach (self::names() as $name) {
            $options[$name] = ucfirst($name);
        }

        return $options;
    }
}
