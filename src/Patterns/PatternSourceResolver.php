<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Patterns;

/**
 * Builds the ordered discovery source list (§3) from the environment without
 * hardcoding paths in the framework code: the shipped core library, third-party
 * plugin packs, the active theme, and any explicit config sources. Priorities:
 * theme (30) > plugin packs (20) > shipped core (10).
 *
 * Pure over its inputs (paths + config) so it is unit-testable against fixtures.
 */
final class PatternSourceResolver
{
    public const PRIORITY_CORE = 10;

    public const PRIORITY_PLUGIN = 20;

    public const PRIORITY_THEME = 30;

    /**
     * @param  string  $corePatternsPath      This plugin's resources/patterns dir.
     * @param  string|null  $pluginsGlob       Glob for other plugins' pattern dirs.
     * @param  string|null  $themePatternsPath  Active theme's pattern dir (or null).
     * @param  array<int, array<string, mixed>>  $configSources  Explicit extra sources.
     */
    public function __construct(
        private readonly string $corePatternsPath,
        private readonly ?string $pluginsGlob = null,
        private readonly ?string $themePatternsPath = null,
        private readonly array $configSources = [],
    ) {}

    /**
     * @return array<int, PatternSource>
     */
    public function resolve(): array
    {
        $sources = [];

        if ($this->corePatternsPath !== '') {
            $sources[] = new PatternSource('core', $this->corePatternsPath, self::PRIORITY_CORE);
        }

        foreach ($this->pluginPackPaths() as $path) {
            $sources[] = new PatternSource('plugin:'.basename(dirname($path, 3)), $path, self::PRIORITY_PLUGIN);
        }

        if ($this->themePatternsPath !== null && $this->themePatternsPath !== '') {
            $sources[] = new PatternSource('theme', $this->themePatternsPath, self::PRIORITY_THEME);
        }

        foreach ($this->configSources as $entry) {
            $path = (string) ($entry['path'] ?? '');
            if ($path === '') {
                continue;
            }
            $sources[] = new PatternSource(
                (string) ($entry['id'] ?? 'custom'),
                $path,
                (int) ($entry['priority'] ?? self::PRIORITY_PLUGIN),
            );
        }

        return $sources;
    }

    /**
     * @return array<int, string>
     */
    private function pluginPackPaths(): array
    {
        if ($this->pluginsGlob === null || $this->pluginsGlob === '') {
            return [];
        }

        $paths = (array) glob($this->pluginsGlob, GLOB_ONLYDIR);
        // Never double-count this plugin's own core library.
        $core = rtrim($this->corePatternsPath, '/\\');

        return array_values(array_filter($paths, static fn ($p): bool => rtrim((string) $p, '/\\') !== $core));
    }
}
