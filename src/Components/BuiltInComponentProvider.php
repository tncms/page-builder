<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Components;

use Plugins\PageBuilder\Components\Contracts\InternalComponentProvider;
use Plugins\PageBuilder\Support\DefaultLibrary;
use Plugins\PageBuilder\Support\PageBuilderManager;

/**
 * The Page Builder first-party library as a component provider (PB-XREG-1).
 *
 * Adapts the existing {@see DefaultLibrary} to the ONE registration authority so
 * built-ins and external plugins travel the same path — a single registry, a
 * single freeze. As the trusted {@see InternalComponentProvider} it keeps the
 * built-ins' bare identifiers (`hero`, `text`, `columns`, …) and their view
 * convention rendering, byte-compatible with every existing layout.
 */
final class BuiltInComponentProvider implements InternalComponentProvider
{
    public function namespace(): string
    {
        return ComponentIdentifier::RESERVED_NAMESPACE;
    }

    public function version(): string
    {
        return PageBuilderManager::VERSION;
    }

    public function components(): iterable
    {
        yield from array_values(DefaultLibrary::sections());
        yield from array_values(DefaultLibrary::blocks());
    }
}
