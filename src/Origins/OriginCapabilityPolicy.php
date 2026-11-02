<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Origins;

/**
 * What each origin may contribute (PB-XREG-4). Themes own presentation; plugins own business data.
 *
 * - built-in: components and design resources (Page Builder's own library).
 * - plugin:   components, interactive behaviors, data sources, design resources.
 * - theme:    components, interactive behaviors, design resources — never data sources. A theme component may
 *             still SELECT a plugin-registered source through the existing `data-source` field.
 *
 * A future contract that lets a theme own data would be a separately approved, versioned change here.
 */
final class OriginCapabilityPolicy
{
    public const VERSION = '1';

    public const COMPONENTS = 'components';

    public const INTERACTIVE = 'interactive';

    public const DATA_SOURCES = 'data-sources';

    public const DESIGN_RESOURCES = 'design-resources';

    private const MATRIX = [
        ProviderOrigin::BUILTIN => [self::COMPONENTS, self::DESIGN_RESOURCES],
        ProviderOrigin::PLUGIN => [self::COMPONENTS, self::INTERACTIVE, self::DATA_SOURCES, self::DESIGN_RESOURCES],
        ProviderOrigin::THEME => [self::COMPONENTS, self::INTERACTIVE, self::DESIGN_RESOURCES],
    ];

    public static function allows(string $originKind, string $capability): bool
    {
        return in_array($capability, self::MATRIX[$originKind] ?? [], true);
    }

    /** @return list<string> */
    public static function capabilities(string $originKind): array
    {
        return self::MATRIX[$originKind] ?? [];
    }
}
