<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Interactive;

use PHPUnit\Framework\Assert;
use Plugins\PageBuilder\Interactive\Contracts\InteractiveComponentDefinition;

/**
 * Reusable PHPUnit assertions for interactive components (PB-XREG-3, Interactive Runtime 1.0.0).
 *
 * A provider certifies its behavior with the SAME validation the registration gate applies, and proves that every
 * declared asset ships in its package under the plugin's `assets.source` directory. Pure: never touches the live
 * registry, the published asset namespace, or the database.
 */
trait InteractiveBehaviorAssertions
{
    /** Assert the behavior passes registration-time validation for the provider's namespace. */
    protected function assertInteractiveBehaviorCompliant(InteractiveComponentDefinition $definition, string $namespace): InteractiveBehavior
    {
        $errors = (new InteractiveBehaviorValidator)->errorsFor($definition, $namespace);

        Assert::assertSame([], $errors, 'Interactive behavior is not compliant: '.implode(' ', $errors));

        return $definition->behavior();
    }

    /** Assert the behavior FAILS registration-time validation — for negative fixtures. */
    protected function assertInteractiveBehaviorRejected(InteractiveComponentDefinition $definition, string $namespace): void
    {
        Assert::assertNotSame(
            [],
            (new InteractiveBehaviorValidator)->errorsFor($definition, $namespace),
            'Interactive behavior was expected to be rejected but passed.',
        );
    }

    /**
     * Assert every declared asset exists in the plugin package as a regular, non-link file with the right extension,
     * beneath the directory named by the plugin's `plugin.json` `assets.source`.
     */
    protected function assertInteractiveAssetsPackaged(string $pluginRoot, InteractiveComponentDefinition $definition): void
    {
        $manifest = json_decode((string) @file_get_contents($pluginRoot.DIRECTORY_SEPARATOR.'plugin.json'), true);
        $source = is_array($manifest) ? ($manifest['assets']['source'] ?? null) : null;

        Assert::assertIsString($source, 'plugin.json must declare "assets": {"source": "…"}.');
        Assert::assertMatchesRegularExpression('/^[a-z0-9][a-z0-9_\/-]*$/', $source, 'assets.source must be a relative directory.');

        $root = realpath($pluginRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $source));
        Assert::assertNotFalse($root, 'assets.source directory does not exist.');

        foreach ($definition->behavior()->orderedAssets() as $asset) {
            $file = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $asset->path());
            Assert::assertFalse(is_link($file), "Asset '{$asset->handle()}' must not be a link.");
            Assert::assertTrue(is_file($file), "Asset '{$asset->handle()}' ({$asset->path()}) is not packaged under {$source}/.");

            $real = (string) realpath($file);
            Assert::assertStringStartsWith(
                rtrim(str_replace('\\', '/', $root), '/').'/',
                str_replace('\\', '/', $real),
                "Asset '{$asset->handle()}' resolves outside {$source}/.",
            );
        }
    }
}
