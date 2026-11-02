<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Interactive;

use Plugins\PageBuilder\Origins\ProviderOrigin;
use TheNguyen\CMS\Support\CmsPath;
use Throwable;

/**
 * Turns a validated (slug, relative path) pair into a same-origin public URL — or null (PB-XREG-3).
 *
 * The ONLY directory an interactive asset may come from is the provider's published namespace that Core's
 * PluginAssetPublisher owns (`destinationFor($slug)`, i.e. `public/vendor/{slug}` by default; populated by the
 * ZIP installer from the plugin's `assets.source`, removed on uninstall). The file must be a regular file whose
 * realpath stays strictly inside that namespace (defeats symlink/junction escapes) and inside the public web
 * root. Cache busting is a content hash, so identical bytes always yield an identical URL. Fail-soft: any doubt
 * resolves to null and the caller treats the behavior as unavailable.
 */
class InteractiveAssetResolver
{
    /** @var array<string, ?string> memoized per process */
    private array $memo = [];

    /**
     * @param  string  $origin  PB-XREG-4 — {@see ProviderOrigin::THEME} resolves from the theme's Core-published
     *                          asset root (`CmsPath::themeAssets()/{slug}`); anything else keeps the PB-XREG-3
     *                          plugin namespace (`cms.plugin_asset_publisher`).
     */
    public function url(string $slug, string $relativePath, string $origin = ProviderOrigin::PLUGIN): ?string
    {
        $key = $origin."\0".$slug."\0".$relativePath;
        if (array_key_exists($key, $this->memo)) {
            return $this->memo[$key];
        }

        return $this->memo[$key] = $this->resolve($slug, $relativePath, $origin);
    }

    /** Forget memoized lookups (after an install/upgrade in the same process, and in tests). */
    public function flush(): void
    {
        $this->memo = [];
    }

    private function resolve(string $slug, string $relativePath, string $origin): ?string
    {
        if (preg_match('/^[a-z0-9][a-z0-9_-]*$/', $slug) !== 1
            || preg_match(InteractiveBehaviorValidator::PATH_PATTERN, $relativePath) !== 1
            || str_contains($relativePath, '..')) {
            return null;
        }

        try {
            $namespace = $origin === ProviderOrigin::THEME ? $this->themeNamespaceDir($slug) : $this->namespaceDir($slug);
            if ($namespace === null) {
                return null;
            }

            $candidate = $namespace.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            if (is_link($candidate) || ! is_file($candidate)) {
                return null;
            }

            $real = realpath($candidate);
            if ($real === false || ! str_starts_with($this->norm($real), $this->norm($namespace).'/')) {
                return null;
            }

            $public = realpath(public_path());
            if ($public === false || ! str_starts_with($this->norm($real), $this->norm($public).'/')) {
                return null;
            }

            $hash = hash_file('sha256', $real);
            if ($hash === false) {
                return null;
            }

            $webPath = ltrim(substr($this->norm($real), strlen($this->norm($public))), '/');

            return asset($webPath).'?v='.substr($hash, 0, 12);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * PB-XREG-4 — the real, existing Core-published asset directory of theme `$slug` (Core config
     * `cms.paths.theme_assets` via {@see CmsPath::themeAssets()}), or null. Junctions/symlinks are refused.
     */
    protected function themeNamespaceDir(string $slug): ?string
    {
        if (preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug) !== 1) {
            return null;
        }

        $dir = rtrim(CmsPath::themeAssets(), '/\\').DIRECTORY_SEPARATOR.$slug;
        if (is_link($dir)) {
            return null;
        }

        $real = realpath($dir);
        $base = realpath(CmsPath::themeAssets());

        return $real !== false && $base !== false && is_dir($real)
            && strcasecmp($this->norm($real), $this->norm($base).'/'.$slug) === 0 ? $real : null;
    }

    /** The real, existing published namespace directory for `$slug`, or null. */
    protected function namespaceDir(string $slug): ?string
    {
        if (! app()->bound('cms.plugin_asset_publisher')) {
            return null;
        }

        $dir = app('cms.plugin_asset_publisher')->destinationFor($slug);
        if (! is_string($dir) || $dir === '' || is_link($dir)) {
            return null;
        }

        $real = realpath($dir);

        return $real !== false && is_dir($real) ? $real : null;
    }

    private function norm(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/');
    }
}
