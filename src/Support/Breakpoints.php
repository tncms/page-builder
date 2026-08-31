<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Support;

use Plugins\PageBuilder\Design\TokenRegistry;

/**
 * The single plugin-owned breakpoint authority (PB-PROD-1).
 *
 * Page Builder responsive values are a `{ desktop, tablet, mobile }` envelope
 * (see {@see ResponsiveValue}); `desktop` is the always-rendered BASE and the two
 * others are desktop-first `max-width` overrides. The pixel thresholds are NOT a
 * new number set: they are read from the existing `breakpoint` design tokens
 * ({@see TokenRegistry::breakpoints()} — desktop 1280 / tablet 768 / mobile 480),
 * which also feed the editor device switcher ({@see \Plugins\PageBuilder\Preview\DevicePresets}).
 * Reusing one source keeps the Vue editor, PHP runtime, and generated CSS from
 * drifting apart; {@see \Tests\Feature\PageBuilder\PageBuilderResponsiveRuntimeTest}
 * guards that agreement.
 *
 * Cascade & emission order: base (`desktop`, no media) first, then `tablet`, then
 * `mobile`. Because every override is a `max-width` block, at a mobile viewport
 * BOTH the tablet and mobile blocks match; emitting mobile LAST (equal specificity)
 * makes it win — exactly reproducing the editor authoring cascade
 * (mobile → tablet → desktop).
 */
final class Breakpoints
{
    /** The always-present, always-rendered base breakpoint (no media query). */
    public const BASE = 'desktop';

    /** Every breakpoint id, base first — the responsive-envelope key set. */
    public const IDS = ['desktop', 'tablet', 'mobile'];

    /** Non-base overrides in cascade-emission order (tablet before mobile). */
    private const OVERRIDES = ['tablet', 'mobile'];

    public function __construct(private readonly TokenRegistry $tokens) {}

    /**
     * @return array<int, string>
     */
    public function ids(): array
    {
        return self::IDS;
    }

    /**
     * Non-base breakpoints in the order their rules must be emitted.
     *
     * @return array<int, string>
     */
    public function overrides(): array
    {
        return self::OVERRIDES;
    }

    public function isBase(string $id): bool
    {
        return $id === self::BASE;
    }

    public function isKnown(string $id): bool
    {
        return in_array($id, self::IDS, true);
    }

    /**
     * The `@media` condition for a non-base breakpoint (e.g. `(max-width: 768px)`),
     * or null for the base breakpoint or an unknown/absent token.
     */
    public function mediaQuery(string $id): ?string
    {
        if ($this->isBase($id) || ! $this->isKnown($id)) {
            return null;
        }

        $width = $this->tokens->breakpoints()[$id] ?? null;

        if (! is_string($width) || trim($width) === '') {
            return null;
        }

        return '(max-width: '.trim($width).')';
    }
}
