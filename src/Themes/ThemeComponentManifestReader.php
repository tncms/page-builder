<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Themes;

use TheNguyen\CMS\Services\ThemeManager;
use Throwable;

/**
 * Reads a theme's declarative component files (PB-XREG-4) from
 * `{Core theme root}/{slug}/page-builder/components/*.json` — the theme root always comes from
 * {@see ThemeManager::themePath()}, never from a declaration.
 *
 * The files are untrusted extension data. Nothing is executed or included. Every file must be a regular file
 * (no symlink or junction), resolve inside the theme's `page-builder/components` directory, stay within the size
 * bound, and decode to a JSON object. The batch is atomic: any violation rejects the whole set with one bounded
 * diagnostic code.
 */
final class ThemeComponentManifestReader
{
    public const SUBDIR = 'page-builder/components';

    public const MAX_FILES = 32;

    public const MAX_BYTES = 32768;

    public function __construct(private readonly ?ThemeManager $themes = null) {}

    /**
     * Decoded component documents, ordered bytewise by file name. [] when the theme ships no components.
     *
     * @return list<array<string, mixed>>
     *
     * @throws ThemeComponentException
     */
    public function read(string $slug): array
    {
        $dir = $this->directory($slug);

        if ($dir === null) {
            return [];
        }

        $files = [];
        foreach ((array) scandir($dir) as $entry) {
            if (! is_string($entry) || $entry === '.' || $entry === '..') {
                continue;
            }
            if (! str_ends_with($entry, '.json')) {
                continue;
            }
            $files[] = $entry;
        }
        sort($files, SORT_STRING);

        if (count($files) > self::MAX_FILES) {
            throw new ThemeComponentException('theme.too_many_components');
        }

        $documents = [];
        foreach ($files as $file) {
            $documents[] = $this->decode($dir, $file);
        }

        return $documents;
    }

    /** The contained, real components directory of `$slug`, or null when absent. */
    private function directory(string $slug): ?string
    {
        try {
            $root = realpath($this->themes()->themePath($slug));
        } catch (Throwable) {
            return null;
        }

        if ($root === false || ! is_dir($root)) {
            return null;
        }

        $candidate = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, self::SUBDIR);

        if (! file_exists($candidate)) {
            return null;
        }

        $real = realpath($candidate);

        if ($this->isLink($candidate) || $this->isLink(dirname($candidate)) || $real === false || ! is_dir($real)
            || ! $this->within($root, $real)) {
            throw new ThemeComponentException('theme.path_escape');
        }

        return $real;
    }

    /** @return array<string, mixed> */
    private function decode(string $dir, string $file): array
    {
        $path = $dir.DIRECTORY_SEPARATOR.$file;
        $real = realpath($path);

        if ($this->isLink($path) || $real === false || ! is_file($real) || ! $this->within($dir, $real)) {
            throw new ThemeComponentException('theme.path_escape');
        }

        $size = filesize($real);
        if ($size === false || $size > self::MAX_BYTES) {
            throw new ThemeComponentException('theme.component_too_large');
        }

        $raw = file_get_contents($real);

        try {
            $data = json_decode((string) $raw, true, 32, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            throw new ThemeComponentException('theme.component_malformed');
        }

        if (! is_array($data) || array_is_list($data)) {
            throw new ThemeComponentException('theme.component_malformed');
        }

        return $data;
    }

    /** True for a symlink or a Windows junction (realpath differs from the lexical path). */
    private function isLink(string $path): bool
    {
        if (is_link($path)) {
            return true;
        }

        $real = realpath($path);

        return $real !== false && strcasecmp($this->norm($real), $this->norm($this->lexical($path))) !== 0;
    }

    private function lexical(string $path): string
    {
        $parent = realpath(dirname($path));

        return $parent === false ? $path : $parent.DIRECTORY_SEPARATOR.basename($path);
    }

    private function within(string $root, string $path): bool
    {
        return str_starts_with($this->norm($path).'/', $this->norm($root).'/');
    }

    private function norm(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/');
    }

    private function themes(): ThemeManager
    {
        return $this->themes ?? app('cms.theme');
    }
}
