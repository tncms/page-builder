<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Repository\Theme;

/**
 * Resolves the currently active TN CMS theme's declarative Page Builder resource
 * roots (PB-TEMPLATE-REGISTRY-1-H1).
 *
 * Page Builder pulls design resources ONLY from the active theme, and ONLY as
 * declarative data files under `themes/{active}/page-builder/{patterns,templates}`.
 * The active theme identity is owned by Core (the `activeSlugResolver` closure reads
 * it through the verified Core theme authority); this locator never activates,
 * switches, writes, or executes theme PHP.
 *
 * Path safety is enforced here, not by callers: every candidate is canonicalised
 * (realpath, which also resolves symlinks) and proven to stay inside the active
 * theme's `page-builder` subtree, so a traversal segment or a symlink/reparse point
 * escaping the theme root is excluded rather than read.
 *
 * The themes root and the active-slug resolver are constructor dependencies so a
 * Page Builder-owned test can point discovery at an isolated fixture theme root
 * WITHOUT weakening production containment and WITHOUT any environment/config knob
 * that would let production scan arbitrary filesystem roots.
 */
final class ActiveThemeResourceLocator
{
    public const KIND_PATTERNS = 'patterns';
    public const KIND_TEMPLATES = 'templates';

    /** @var array<int, string> */
    public const KINDS = [self::KIND_PATTERNS, self::KIND_TEMPLATES];

    /** The theme-owned Page Builder subdirectory that carries declarative resources. */
    private const SUBDIR = 'page-builder';

    private const SLUG = '/^[a-z0-9]+(-[a-z0-9]+)*$/';

    /** @var \Closure(): ?string */
    private readonly \Closure $activeSlugResolver;

    /**
     * @param  string  $themesRoot  Absolute base directory that contains theme folders
     *                              (production: base_path('themes'); tests inject a fixture root).
     * @param  \Closure(): ?string  $activeSlugResolver  Returns the active theme slug via Core authority.
     */
    public function __construct(
        private readonly string $themesRoot,
        \Closure $activeSlugResolver,
    ) {
        $this->activeSlugResolver = $activeSlugResolver;
    }

    /**
     * The active theme slug, validated against the slug grammar, or null when there
     * is no active theme or the resolved value is not a safe slug.
     */
    public function activeThemeSlug(): ?string
    {
        $slug = ($this->activeSlugResolver)();

        if (! is_string($slug) || $slug === '' || ! preg_match(self::SLUG, $slug)) {
            return null;
        }

        return $slug;
    }

    /**
     * The canonical absolute `page-builder` resource root inside the active theme,
     * or null when there is no active theme / the directory is absent / it escapes.
     */
    public function resourceRoot(): ?string
    {
        $slug = $this->activeThemeSlug();
        if ($slug === null) {
            return null;
        }

        $themeRoot = $this->canonical($this->themesRoot.'/'.$slug);
        if ($themeRoot === null) {
            return null;
        }

        $root = $this->canonical($themeRoot.'/'.self::SUBDIR);
        if ($root === null || ! $this->within($themeRoot, $root)) {
            return null;
        }

        return $root;
    }

    /**
     * The canonical directory for a resource kind, or null when unsupported/absent/escaping.
     */
    public function directory(string $kind): ?string
    {
        if (! in_array($kind, self::KINDS, true)) {
            return null;
        }

        $root = $this->resourceRoot();
        if ($root === null) {
            return null;
        }

        $dir = $this->canonical($root.'/'.$kind);
        if ($dir === null || ! $this->within($root, $dir) || ! is_dir($dir)) {
            return null;
        }

        return $dir;
    }

    /**
     * The declarative `*.json` resource files for a kind, each proven to stay inside
     * the resource root, sorted deterministically by bytewise relative path. An
     * absent directory yields an empty list (never an error).
     *
     * @return array<int, array{relative: string, absolute: string}>
     */
    public function files(string $kind): array
    {
        $dir = $this->directory($kind);
        $root = $this->resourceRoot();
        if ($dir === null || $root === null) {
            return [];
        }

        $found = [];
        foreach ((array) glob($dir.'/*.json') as $path) {
            $abs = $this->canonical((string) $path);
            if ($abs === null || ! is_file($abs) || ! $this->within($root, $abs)) {
                continue;
            }
            $found[$this->relative($root, $abs)] = $abs;
        }

        ksort($found, SORT_STRING);

        $out = [];
        foreach ($found as $relative => $absolute) {
            $out[] = ['relative' => $kind.'/'.$relative, 'absolute' => $absolute];
        }

        return $out;
    }

    /** realpath + POSIX separators, or null when the path does not resolve. */
    private function canonical(string $path): ?string
    {
        $real = realpath($path);

        return $real === false ? null : str_replace('\\', '/', $real);
    }

    /** True when $candidate is $root itself or a descendant of it. */
    private function within(string $root, string $candidate): bool
    {
        $root = rtrim($root, '/');

        return $candidate === $root || str_starts_with($candidate, $root.'/');
    }

    private function relative(string $root, string $absolute): string
    {
        $prefix = rtrim($root, '/').'/';

        return str_starts_with($absolute, $prefix) ? substr($absolute, strlen($prefix)) : basename($absolute);
    }
}
