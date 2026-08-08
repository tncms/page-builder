<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Facades;

use Illuminate\Support\Facades\Facade;
use Plugins\PageBuilder\Support\PageBuilderManager;

/**
 * Facade for the {@see PageBuilderManager}.
 *
 * @method static \Plugins\PageBuilder\Registries\SectionRegistry sections()
 * @method static \Plugins\PageBuilder\Registries\BlockRegistry blocks()
 * @method static \Plugins\PageBuilder\Support\LayoutStore layouts()
 * @method static \Plugins\PageBuilder\Support\LayoutRenderer renderer()
 * @method static \Plugins\PageBuilder\Runtime\ComponentFactory factory()
 * @method static \Plugins\PageBuilder\Runtime\ComponentRuntime runtime()
 * @method static \Plugins\PageBuilder\Runtime\ComponentTree componentTree(?array $payload)
 * @method static string render(?array $payload)
 * @method static array assets()
 * @method static array tokens()
 * @method static \Plugins\PageBuilder\Design\TokenRegistry tokenRegistry()
 * @method static array sectionDefinitions()
 * @method static array blockDefinitions()
 * @method static array definitions()
 * @method static array|null schemaFor(string $kind, string $type)
 * @method static \Plugins\PageBuilder\Support\PageBuilderManager registerSection(string $type, array|\Plugins\PageBuilder\Contracts\ComponentDefinition|string $definition = [])
 * @method static \Plugins\PageBuilder\Support\PageBuilderManager registerBlock(string $type, array|\Plugins\PageBuilder\Contracts\ComponentDefinition|string $definition = [])
 * @method static bool isEnabled()
 * @method static array status()
 *
 * @see PageBuilderManager
 */
class PageBuilder extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'page-builder';
    }
}
