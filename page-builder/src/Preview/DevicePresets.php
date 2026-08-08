<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Preview;

/**
 * Device viewport presets for runtime preview.
 *
 * Widths mirror the builder's breakpoint design tokens (TokenRegistry:
 * desktop 1280 / tablet 768 / mobile 480). Switching device changes ONLY the
 * viewport width used to render the same resource — it never alters resource data.
 */
final class DevicePresets
{
    public const DESKTOP = 'desktop';
    public const TABLET = 'tablet';
    public const MOBILE = 'mobile';

    public const DEFAULT = self::DESKTOP;

    /** @var array<string, int> device => width in CSS px (mirrors TokenRegistry breakpoints) */
    private const WIDTHS = [
        self::DESKTOP => 1280,
        self::TABLET => 768,
        self::MOBILE => 480,
    ];

    public function isValid(string $device): bool
    {
        return array_key_exists($device, self::WIDTHS);
    }

    public function normalize(string $device): string
    {
        return $this->isValid($device) ? $device : self::DEFAULT;
    }

    public function width(string $device): int
    {
        return self::WIDTHS[$this->normalize($device)];
    }

    /**
     * @return array<int, string>
     */
    public function all(): array
    {
        return array_keys(self::WIDTHS);
    }
}
