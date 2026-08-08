<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Support;

/**
 * Typography settings value (family / size / weight / line-height / letter
 * spacing / transform) — Phase 6. Tolerant `from()` reads only known keys;
 * unknown keys are ignored. Pure value object.
 */
final class TypographyValue
{
    public function __construct(
        private readonly ?string $family = null,
        private readonly mixed $size = null,
        private readonly mixed $weight = null,
        private readonly mixed $lineHeight = null,
        private readonly mixed $letterSpacing = null,
        private readonly ?string $transform = null,
    ) {}

    public static function make(
        ?string $family = null,
        mixed $size = null,
        mixed $weight = null,
        mixed $lineHeight = null,
        mixed $letterSpacing = null,
        ?string $transform = null,
    ): self {
        return new self($family, $size, $weight, $lineHeight, $letterSpacing, $transform);
    }

    public static function from(mixed $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if (! is_array($value)) {
            return new self;
        }

        return new self(
            isset($value['family']) ? (string) $value['family'] : null,
            $value['size'] ?? null,
            $value['weight'] ?? null,
            $value['lineHeight'] ?? null,
            $value['letterSpacing'] ?? null,
            isset($value['transform']) ? (string) $value['transform'] : null,
        );
    }

    /**
     * @return array{family: ?string, size: mixed, weight: mixed, lineHeight: mixed, letterSpacing: mixed, transform: ?string}
     */
    public function toArray(): array
    {
        return [
            'family' => $this->family,
            'size' => $this->size,
            'weight' => $this->weight,
            'lineHeight' => $this->lineHeight,
            'letterSpacing' => $this->letterSpacing,
            'transform' => $this->transform,
        ];
    }
}
