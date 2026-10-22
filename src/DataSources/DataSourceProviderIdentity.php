<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\DataSources;

use Plugins\PageBuilder\DataSources\Contracts\DataSourceProvider;
use ReflectionClass;
use TheNguyen\CMS\Services\ExtensionManager;
use Throwable;

/**
 * Derives the canonical installed-plugin slug that owns a data-source provider,
 * server-side (PB-XREG-2).
 *
 * Same rule as {@see \Plugins\PageBuilder\Components\ComponentProviderIdentity}:
 * the provider's class file must live under the directory of an ACTIVE installed
 * plugin (Core {@see ExtensionManager}); that plugin's slug is the trusted identity.
 * The declared namespace is never trusted. Kept as its own type so the Component
 * and Data Source registries share no state (including test identities).
 */
final class DataSourceProviderIdentity
{
    /**
     * Test-only class → slug identities. Empty (and ignored) in production.
     *
     * @var array<class-string, string>
     */
    private static array $testIdentities = [];

    public function __construct(private readonly ?ExtensionManager $extensions = null) {}

    /** The canonical active-plugin slug owning $provider, or null when it cannot be derived. */
    public function resolve(DataSourceProvider $provider): ?string
    {
        $class = $provider::class;

        if (isset(self::$testIdentities[$class])) {
            return self::$testIdentities[$class];
        }

        try {
            $file = (new ReflectionClass($provider))->getFileName();

            if ($file === false) {
                return null;
            }

            $real = $this->normalize((string) (realpath($file) ?: $file));

            foreach ($this->extensions()->activePlugins() as $plugin) {
                $base = $this->normalize((string) (realpath($plugin->path) ?: $plugin->path));

                if ($base !== '' && str_starts_with($real, $base.'/')) {
                    return $plugin->slug;
                }
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    /** Register a disposable test identity for a provider class. TEST ONLY. */
    public static function fakeIdentityForTesting(string $class, string $slug): void
    {
        self::$testIdentities[$class] = $slug;
    }

    /** Clear all test identities. Call in test tearDown. */
    public static function clearTestingIdentities(): void
    {
        self::$testIdentities = [];
    }

    private function extensions(): ExtensionManager
    {
        return $this->extensions ?? app(ExtensionManager::class);
    }

    private function normalize(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/');
    }
}
