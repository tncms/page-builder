<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Runtime;

use Plugins\PageBuilder\Design\TokenResolver;

/**
 * Runtime adapter that resolves design-token references inside component data
 * (Phase 7). Delegates to the {@see TokenResolver}: string values that are token
 * references ("color.primary" / "{{color.primary}}") become their token value;
 * unknown references and every non-reference string resolve to themselves, so
 * ordinary content is returned unchanged.
 */
class DesignTokenResolver
{
    public function __construct(private readonly TokenResolver $resolver) {}

    public function resolveValue(mixed $value): mixed
    {
        return is_string($value) ? $this->resolver->resolve($value) : $value;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function resolveData(array $data): array
    {
        return array_map(fn ($value): mixed => $this->resolveValue($value), $data);
    }
}
