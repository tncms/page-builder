<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\DataSources;

/**
 * The validated, bounded query handed to {@see Contracts\DataSourceDefinition::fetch()}
 * (PB-XREG-2).
 *
 * Built ONLY by {@see DataSourceConfigValidator} after the saved configuration has
 * been checked against the source's own declaration, so a provider receives a
 * limit within its ceiling and a complete option map (every declared key present,
 * every value allowed). It never contains SQL, columns, relations, or callables.
 */
final class DataSourceQuery
{
    /**
     * @param  array<string, string|bool>  $options
     */
    public function __construct(
        public readonly int $limit,
        public readonly array $options,
    ) {}

    public function option(string $key, string|bool|null $default = null): string|bool|null
    {
        return $this->options[$key] ?? $default;
    }
}
