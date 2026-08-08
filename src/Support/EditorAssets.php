<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Support;

/**
 * Cache-busted URLs for the built frontend-editor bundles (Phase 16 hotfix).
 *
 * The shell (`shell.js`/`shell.css`) and iframe (`iframe.js`/`iframe.css`)
 * bundles ship at FIXED, unversioned output paths. Previously they were
 * cache-busted with a single hand-maintained plugin constant
 * ({@see PageBuilderManager::VERSION}) that is never bumped on rebuild — so
 * every rebuild produced the exact same `?v=` query and browsers kept serving
 * the STALE cached bundle. That is the real-browser cause of the editor bridge
 * failing to initialise after a rebuild: the shell shipped new handshake code
 * while the iframe kept running an old cached bundle that could not answer it.
 *
 * This helper derives the version from each built file's own `filemtime()`,
 * INDEPENDENTLY per file, so any rebuild that touches a bundle changes only that
 * bundle's URL and guarantees a fresh fetch. When the file is missing (e.g. in a
 * test environment where assets were never published) it falls back to the
 * static plugin version so URL generation never fails.
 */
final class EditorAssets
{
    /**
     * The frontend-editor bundles that MUST be published for the Live Builder to
     * work. `missing()` checks these so a stale/partial build surfaces as an
     * actionable error rather than a silent 404.
     *
     * @var array<int, string>
     */
    public const REQUIRED = [
        'vendor/page-builder/frontend-editor/shell.js',
        'vendor/page-builder/frontend-editor/shell.css',
        'vendor/page-builder/frontend-editor/iframe.js',
        'vendor/page-builder/frontend-editor/iframe.css',
    ];

    /**
     * Public-relative path (no leading slash), e.g.
     * "vendor/page-builder/frontend-editor/iframe.js".
     */
    public static function url(string $relativePath): string
    {
        return asset($relativePath).'?v='.self::version($relativePath);
    }

    /** Absolute filesystem path of a public asset (whether or not it exists). */
    public static function path(string $relativePath): string
    {
        return public_path($relativePath);
    }

    /** Whether the built asset is actually present on disk. */
    public static function exists(string $relativePath): bool
    {
        return is_file(self::path($relativePath));
    }

    /**
     * The subset of the given assets (default: {@see REQUIRED}) that are NOT on
     * disk. An empty result means every required bundle is published; a non-empty
     * result is the actionable signal the Live Builder uses to warn instead of
     * emitting a `?v=<VERSION>` URL that 404s. filemtime versioning already keeps
     * present files fresh — this catches the "not built at all" case.
     *
     * @param  array<int, string>|null  $relativePaths
     * @return array<int, string>
     */
    public static function missing(?array $relativePaths = null): array
    {
        $paths = $relativePaths ?? self::REQUIRED;

        return array_values(array_filter($paths, static fn (string $p): bool => ! self::exists($p)));
    }

    /**
     * The content-derived version token for a single built asset: its filemtime
     * when present on disk, otherwise the static plugin version as a fallback.
     */
    public static function version(string $relativePath): string
    {
        $absolute = public_path($relativePath);

        if (is_file($absolute)) {
            $mtime = @filemtime($absolute);

            if ($mtime !== false) {
                return (string) $mtime;
            }
        }

        return PageBuilderManager::VERSION;
    }
}
