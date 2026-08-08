<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Support;

/**
 * A per-breakpoint value (desktop / tablet / mobile) — Phase 6.
 *
 * Tolerant normalization: a scalar becomes the desktop value; an array reads
 * only the known breakpoint keys (unknown keys ignored); an existing instance
 * passes through. `get()` falls back to the supplied default when a breakpoint
 * is unset. Pure value object — no rendering.
 */
final class ResponsiveValue
{
    public function __construct(
        private readonly mixed $desktop = null,
        private readonly mixed $tablet = null,
        private readonly mixed $mobile = null,
    ) {}

    public static function make(mixed $desktop = null, mixed $tablet = null, mixed $mobile = null): self
    {
        return new self($desktop, $tablet, $mobile);
    }

    /**
     * Normalize any raw stored value into a ResponsiveValue.
     */
    public static function from(mixed $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if (is_array($value)) {
            return new self(
                $value['desktop'] ?? null,
                $value['tablet'] ?? null,
                $value['mobile'] ?? null,
            );
        }

        // Scalar (or null): treated as the desktop value.
        return new self($value);
    }

    public function get(string $device, mixed $fallback = null): mixed
    {
        return match ($device) {
            'desktop' => $this->desktop ?? $fallback,
            'tablet' => $this->tablet ?? $fallback,
            'mobile' => $this->mobile ?? $fallback,
            default => $fallback,
        };
    }

    /**
     * @return array{desktop: mixed, tablet: mixed, mobile: mixed}
     */
    public function toArray(): array
    {
        return [
            'desktop' => $this->desktop,
            'tablet' => $this->tablet,
            'mobile' => $this->mobile,
        ];
    }
}
