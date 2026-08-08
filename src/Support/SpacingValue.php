<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Support;

/**
 * Box spacing (top / right / bottom / left) value — Phase 6.
 *
 * Tolerant normalization: a scalar sets all four sides (linked); an array reads
 * the four side keys plus an optional `linked` flag; an instance passes through.
 * Pure value object — no CSS is emitted here.
 */
final class SpacingValue
{
    public function __construct(
        private readonly mixed $top = null,
        private readonly mixed $right = null,
        private readonly mixed $bottom = null,
        private readonly mixed $left = null,
        private readonly bool $linked = false,
    ) {}

    public static function make(
        mixed $top = null,
        mixed $right = null,
        mixed $bottom = null,
        mixed $left = null,
        bool $linked = false,
    ): self {
        return new self($top, $right, $bottom, $left, $linked);
    }

    public static function from(mixed $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if (is_array($value)) {
            return new self(
                $value['top'] ?? null,
                $value['right'] ?? null,
                $value['bottom'] ?? null,
                $value['left'] ?? null,
                (bool) ($value['linked'] ?? false),
            );
        }

        if (is_scalar($value)) {
            // A single value links all four sides.
            return new self($value, $value, $value, $value, true);
        }

        return new self;
    }

    public function get(string $side, mixed $fallback = null): mixed
    {
        return match ($side) {
            'top' => $this->top ?? $fallback,
            'right' => $this->right ?? $fallback,
            'bottom' => $this->bottom ?? $fallback,
            'left' => $this->left ?? $fallback,
            default => $fallback,
        };
    }

    public function isLinked(): bool
    {
        return $this->linked;
    }

    /**
     * @return array{top: mixed, right: mixed, bottom: mixed, left: mixed, linked: bool}
     */
    public function toArray(): array
    {
        return [
            'top' => $this->top,
            'right' => $this->right,
            'bottom' => $this->bottom,
            'left' => $this->left,
            'linked' => $this->linked,
        ];
    }
}
