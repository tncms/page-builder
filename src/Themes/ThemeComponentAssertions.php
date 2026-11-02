<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Themes;

use PHPUnit\Framework\Assert;
use Plugins\PageBuilder\Interactive\Contracts\InteractiveComponentDefinition;
use Plugins\PageBuilder\Interactive\InteractiveBehaviorValidator;

/**
 * Reusable PHPUnit assertions for theme authors (PB-XREG-4, theme component contract 1).
 *
 * A theme certifies its declarative `page-builder/components/*.json` with the SAME reader, factory and behavior
 * validation the registration gate applies, and proves that every declared view and interactive asset ships inside
 * the theme. Pure: never touches the live registry, the active-theme setting, published assets, or the database.
 * The theme must be installed under the Core themes root (it need not be active).
 */
trait ThemeComponentAssertions
{
    /**
     * Assert every component document of `$slug` is accepted, its fixed view and every declared asset is packaged.
     *
     * @return list<ThemeComponentDefinition>
     */
    protected function assertThemeComponentsCompliant(string $slug): array
    {
        try {
            $definitions = array_map(
                static fn (array $document): ThemeComponentDefinition => (new ThemeComponentFactory)->make($slug, $document),
                (new ThemeComponentManifestReader)->read($slug),
            );
        } catch (ThemeComponentException $e) {
            Assert::fail("Theme '{$slug}' component contract rejected: {$e->diagnostic}");
        }

        $root = (string) realpath(app('cms.theme')->themePath($slug));

        foreach ($definitions as $definition) {
            $name = substr($definition->type(), strlen($slug) + 1);
            $view = $root.'/views/page-builder/components/'.$slug.'/'.$name.'.blade.php';
            Assert::assertTrue(is_file($view) && ! is_link($view), "Component '{$definition->type()}' view is not packaged.");

            if ($definition instanceof InteractiveComponentDefinition) {
                Assert::assertSame([], (new InteractiveBehaviorValidator)->errorsFor($definition, $slug), "Component '{$definition->type()}' behavior is not compliant.");
                $this->assertThemeAssetsPackaged($root, $definition);
            }
        }

        return $definitions;
    }

    /** Assert one document is refused with the expected bounded diagnostic code — for negative fixtures. */
    protected function assertThemeComponentRejected(string $slug, array $document, string $expectedCode): void
    {
        try {
            (new ThemeComponentFactory)->make($slug, $document);
        } catch (ThemeComponentException $e) {
            Assert::assertSame($expectedCode, $e->diagnostic);

            return;
        }

        Assert::fail("Theme component document was expected to be rejected with {$expectedCode}.");
    }

    private function assertThemeAssetsPackaged(string $themeRoot, InteractiveComponentDefinition $definition): void
    {
        $assets = realpath($themeRoot.DIRECTORY_SEPARATOR.'assets');
        Assert::assertNotFalse($assets, 'Theme has no assets/ directory.');
        $base = rtrim(str_replace('\\', '/', (string) $assets), '/').'/';

        foreach ($definition->behavior()->orderedAssets() as $asset) {
            $file = $assets.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $asset->path());
            Assert::assertFalse(is_link($file), "Asset '{$asset->handle()}' must not be a link.");
            Assert::assertTrue(is_file($file), "Asset '{$asset->handle()}' ({$asset->path()}) is not packaged under assets/.");
            Assert::assertStringStartsWith($base, str_replace('\\', '/', (string) realpath($file)), "Asset '{$asset->handle()}' resolves outside assets/.");
        }
    }
}
