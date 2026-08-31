<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Support;

use Plugins\PageBuilder\Support\DynamicStyle\DynamicStyleRegistry;

/**
 * Renders per-breakpoint box-model spacing faithfully on the public frontend
 * (PB-PROD-1).
 *
 * The editor stores box-model spacing at `data.spacing.{padding,margin}`. A value
 * is either a flat box `{top,right,bottom,left}` (applies at every width, handled
 * inline by {@see SpacingStyle}) or a responsive ENVELOPE
 * `{ desktop, tablet?, mobile? }` whose per-breakpoint values are boxes.
 *
 * This class turns the envelope into frontend-surviving CSS by REUSING the existing
 * runtime style pipeline ({@see DynamicStyleRegistry}) — the same head-injected,
 * sanitiser-proof, content-hashed `.pb-ds-*` mechanism that column widths and the
 * generic section shell already use. It is NOT a second responsive subsystem: the
 * envelope shape, the breakpoint ids, and the desktop-first cascade all come from
 * the one authority ({@see ResponsiveValue}, {@see Breakpoints}). The desktop/base
 * value is a normal (media-less) rule; each tablet/mobile OVERRIDE is registered
 * inside its breakpoint's `@media (max-width: …)` block.
 *
 * A component whose spacing has no responsive envelope produces NOTHING here — it
 * keeps its byte-identical inline-only behaviour.
 */
final class ResponsiveSpacingStyle
{
    private const SIDES = ['top', 'right', 'bottom', 'left'];

    public function __construct(
        private readonly DynamicStyleRegistry $registry,
        private readonly Breakpoints $breakpoints,
    ) {}

    /**
     * Merge CSS classes into the first opening tag of an HTML fragment (adding a
     * `class="…"` attribute when none exists). Returns the HTML unchanged when
     * there are no classes or no opening tag is found — mirrors
     * {@see SpacingStyle::inject}'s first-tag handling.
     *
     * @param  array<int, string>  $classes
     */
    public static function applyClasses(string $html, array $classes): string
    {
        if ($classes === []) {
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
        $added = implode(' ', $classes);

        if (preg_match('/\sclass\s*=\s*"/i', $openTag)) {
            $newOpen = preg_replace('/(\sclass\s*=\s*")/i', '$1'.$added.' ', $openTag, 1);
        } else {
            $selfClosing = str_ends_with(rtrim($openTag), '/');
            $newOpen = rtrim(rtrim($openTag), '/').' class="'.$added.'"'.($selfClosing ? ' /' : '');
        }

        return substr($html, 0, $tagStart).$newOpen.substr($html, $gt);
    }

    /**
     * Whether a component's spacing map carries at least one responsive envelope
     * (so the responsive path applies at all).
     */
    public function isResponsive(mixed $spacing): bool
    {
        if (! is_array($spacing)) {
            return false;
        }

        return $this->isEnvelope($spacing['padding'] ?? null)
            || $this->isEnvelope($spacing['margin'] ?? null);
    }

    /**
     * The desktop/base spacing map (`{padding, margin}` of boxes) for the inline
     * path — envelopes collapse to their desktop entry, flat boxes pass through.
     * Byte-identical to the raw map when nothing is responsive.
     *
     * @return array<string, mixed>
     */
    public function baseSpacing(mixed $spacing): array
    {
        if (! is_array($spacing)) {
            return [];
        }

        $base = [];
        foreach (['padding', 'margin'] as $prop) {
            $box = $this->boxAt($spacing[$prop] ?? null, Breakpoints::BASE, true);
            if ($box !== null) {
                $base[$prop] = $box;
            }
        }

        return $base;
    }

    /**
     * Register the responsive spacing rules (base + per-breakpoint overrides) and
     * return the `.pb-ds-*` classes to add to the component root, in cascade order.
     * Empty when the spacing is not responsive or nothing survives validation.
     *
     * @return array<int, string>
     */
    public function register(mixed $spacing): array
    {
        if (! $this->isResponsive($spacing) || ! is_array($spacing)) {
            return [];
        }

        $classes = [];

        // Base first (desktop, no media), then each override in emission order so a
        // narrower breakpoint wins where several max-width blocks match.
        foreach ($this->breakpoints->ids() as $device) {
            $isBase = $this->breakpoints->isBase($device);
            $decls = $this->declarationsFor($spacing, $device, $isBase);
            if ($decls === []) {
                continue;
            }

            $class = $this->registry->register($decls, $isBase ? null : $this->breakpoints->mediaQuery($device));
            if ($class !== null) {
                $classes[] = $class;
            }
        }

        return $classes;
    }

    /**
     * The combined padding+margin declarations for one breakpoint. The base carries
     * every property's desktop/flat value; a non-base breakpoint carries ONLY the
     * properties that declare an explicit override there (so unset properties
     * inherit the base via the cascade).
     *
     * @return array<string, string>
     */
    private function declarationsFor(array $spacing, string $device, bool $isBase): array
    {
        $box = [];
        foreach (['padding', 'margin'] as $prop) {
            $sides = $this->boxAt($spacing[$prop] ?? null, $device, $isBase);
            if ($sides !== null) {
                $box[$prop] = $sides;
            }
        }

        return SpacingStyle::declarations($box);
    }

    /**
     * The box for one property at one breakpoint, or null when it does not
     * contribute a rule there.
     *
     * - Envelope: base → the `desktop` entry; non-base → only an EXPLICIT override.
     * - Flat box: base → the box itself; non-base → null (a flat value is not
     *   per-breakpoint, so it lives only in the base rule).
     *
     * @return array<string, mixed>|null
     */
    private function boxAt(mixed $value, string $device, bool $isBase): ?array
    {
        if ($this->isEnvelope($value)) {
            /** @var array<string, mixed> $value */
            if ($isBase) {
                $desktop = $value[Breakpoints::BASE] ?? null;

                return is_array($desktop) ? $desktop : null;
            }

            $override = $value[$device] ?? null;

            return is_array($override) ? $override : null;
        }

        if ($isBase && is_array($value) && $this->looksLikeBox($value)) {
            return $value;
        }

        return null;
    }

    /**
     * A responsive envelope: a non-empty array whose keys are ALL breakpoint ids
     * (mirrors {@see ResponsiveValue} / the editor's `isResponsiveEnvelope`). A
     * composite box `{top,right,…}` is therefore never mistaken for an envelope.
     */
    private function isEnvelope(mixed $value): bool
    {
        if (! is_array($value) || $value === []) {
            return false;
        }

        foreach (array_keys($value) as $key) {
            if (! in_array($key, Breakpoints::IDS, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * A flat spacing box declares at least one side key. Guards against treating an
     * empty/foreign array as a box.
     *
     * @param  array<int|string, mixed>  $value
     */
    private function looksLikeBox(array $value): bool
    {
        foreach (self::SIDES as $side) {
            if (array_key_exists($side, $value)) {
                return true;
            }
        }

        return false;
    }
}
