<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Package;

/**
 * Persistent, local, file-based store for imported Design Packages.
 *
 * Each imported package is a single `.tncms-design` JSON file under a configurable
 * directory (default: storage/app/page-builder/design-library/imported). This is
 * the ONLY persistence used by the import feature — no database, no cloud, no
 * network. {@see \Plugins\PageBuilder\Repository\Providers\ImportedResourceProvider}
 * reads it; {@see DesignPackageImporter} writes it.
 */
final class ImportedPackageStore
{
    public function __construct(
        private readonly ?string $basePath = null,
    ) {
    }

    public function directory(): string
    {
        $dir = $this->basePath
            ?? (string) config('page-builder.design_library.imported_path', storage_path('app/page-builder/design-library/imported'));

        return rtrim($dir, '/\\');
    }

    /**
     * Persist a validated package document, keyed by its (slug) id. Overwrites a
     * prior package with the same id (re-import updates in place). Atomic write.
     *
     * @param  array<string, mixed>  $package
     */
    public function save(string $packageId, array $package): string
    {
        $dir = $this->directory();
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $path = $this->pathFor($packageId);
        $json = json_encode($package, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $tmp = $path.'.tmp';
        file_put_contents($tmp, (string) $json, LOCK_EX);
        @rename($tmp, $path);

        return $path;
    }

    /**
     * All stored package documents (malformed files are skipped, never fatal).
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        $packages = [];
        foreach ($this->files() as $file) {
            $decoded = json_decode((string) file_get_contents($file), true);
            if (is_array($decoded)) {
                $packages[] = $decoded;
            }
        }

        return $packages;
    }

    public function has(string $packageId): bool
    {
        return is_file($this->pathFor($packageId));
    }

    public function forget(string $packageId): void
    {
        $path = $this->pathFor($packageId);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * A cheap content signature (paths + mtimes + sizes) so the provider can expose
     * a revision that changes whenever the store changes.
     */
    public function signature(): string
    {
        $parts = [];
        foreach ($this->files() as $file) {
            $parts[] = $file.':'.(string) @filemtime($file).':'.(string) @filesize($file);
        }
        sort($parts);

        return $parts === [] ? '' : hash('crc32b', implode('|', $parts));
    }

    /**
     * @return array<int, string>
     */
    private function files(): array
    {
        $glob = glob($this->directory().'/*.'.DesignPackageManifest::EXTENSION);

        return $glob === false ? [] : $glob;
    }

    private function pathFor(string $packageId): string
    {
        $slug = preg_replace('/[^a-z0-9._-]+/i', '-', $packageId);
        $slug = trim((string) $slug, '-');
        if ($slug === '') {
            $slug = 'package';
        }

        return $this->directory().'/'.$slug.'.'.DesignPackageManifest::EXTENSION;
    }
}
