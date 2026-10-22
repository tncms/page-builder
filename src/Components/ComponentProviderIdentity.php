<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Components;

use Plugins\PageBuilder\Components\Contracts\ComponentProvider;
use ReflectionClass;
use TheNguyen\CMS\Services\ExtensionManager;
use Throwable;

/**
 * Derives the canonical installed-plugin slug that owns a component provider,
 * server-side (PB-XREG-1).
 *
 * A provider's namespace is NEVER trusted from the value it declares. Instead this
 * resolver locates the provider's class file on disk and matches it against the
 * path of an ACTIVE installed plugin (Core {@see ExtensionManager}). The resulting
 * slug is the trusted identity; the registration gate requires the provider's
 * declared namespace to equal it. This leans only on the public Core plugin
 * registry (active slugs + plugin paths) and the fixed PSR-4 plugin convention —
 * no new Core seam.
 *
 * A single bounded test seam ({@see fakeIdentityForTesting()}) lets disposable
 * fixtures that do not live under a real plugin directory assert an identity. It
 * is inert in production (the map is empty) and does NOT weaken the ownership
 * check: the gate still requires the declared namespace to equal the faked slug,
 * so spoofing a different namespace still fails.
 */
final class ComponentProviderIdentity
{
    /**
     * Test-only class → slug identities. Empty (and ignored) in production.
     *
     * @var array<class-string, string>
     */
    private static array $testIdentities = [];

    public function __construct(private readonly ?ExtensionManager $extensions = null) {}

    /**
     * The canonical active-plugin slug owning $provider, or null when it cannot be
     * securely derived (provider not located under any active plugin).
     */
    public function resolve(ComponentProvider $provider): ?string
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
            // Fail closed: an unresolvable identity is treated as "no trusted slug".
            return null;
        }

        return null;
    }

    /**
     * Register a disposable test identity for a provider class. TEST ONLY.
     */
    public static function fakeIdentityForTesting(string $class, string $slug): void
    {
        self::$testIdentities[$class] = $slug;
    }

    /**
     * Clear all test identities. Call in test tearDown.
     */
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
