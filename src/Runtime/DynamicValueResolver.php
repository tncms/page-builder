<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Runtime;

use Plugins\PageBuilder\Contracts\ComponentInstance;

/**
 * Resolves dynamic-data placeholders (e.g. {{user.display_name}}) in component
 * data — Phase 7 is a pure pass-through.
 *
 * The seam exists so later phases can register providers that replace bindings
 * with request/model-derived values; today it returns data unchanged so the
 * rendered output is identical to the pre-runtime renderer.
 */
class DynamicValueResolver
{
    public function resolveValue(mixed $value, ?ComponentInstance $context = null): mixed
    {
        return $value;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function resolveData(array $data, ?ComponentInstance $context = null): array
    {
        return $data;
    }
}
