<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Runtime;

use Plugins\PageBuilder\Support\ResponsiveValue;

/**
 * Resolves per-breakpoint values down to a single device (Phase 7).
 *
 * This phase renders the desktop breakpoint only, so a responsive-shaped value
 * collapses to its desktop entry; everything else passes through untouched. It
 * only transforms values that are clearly responsive maps (keys ⊆
 * desktop/tablet/mobile) or {@see ResponsiveValue} instances, so ordinary data
 * (scalars, block lists, …) is returned byte-identical.
 */
class ResponsiveResolver
{
    public function __construct(private readonly string $device = 'desktop') {}

    public function device(): string
    {
        return $this->device;
    }

    public function resolveValue(mixed $value): mixed
    {
        if ($value instanceof ResponsiveValue) {
            return $value->get($this->device);
        }

        if (is_array($value) && $this->isResponsiveMap($value)) {
            return ResponsiveValue::from($value)->get($this->device);
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function resolveData(array $data): array
    {
        return array_map(fn ($value): mixed => $this->resolveValue($value), $data);
    }

    /**
     * True only for a non-empty array whose keys are all breakpoint names.
     *
     * @param  array<int|string, mixed>  $value
     */
    private function isResponsiveMap(array $value): bool
    {
        if ($value === []) {
            return false;
        }

        foreach (array_keys($value) as $key) {
            if (! in_array($key, ['desktop', 'tablet', 'mobile'], true)) {
                return false;
            }
        }

        return true;
    }
}
