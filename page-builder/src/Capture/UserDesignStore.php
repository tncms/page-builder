<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Capture;

/**
 * Persistent, local, file-based store for captured "My Designs" (Phase 26.48).
 *
 * Each captured design is a single JSON file under a configurable directory
 * (default: storage/app/page-builder/design-library/my-designs). This is the ONLY
 * persistence for Save as Design — no database, no cloud, no network. The Design
 * Capture Pipeline writes it; {@see \Plugins\PageBuilder\Repository\Providers\UserDesignProvider}
 * reads it. It mirrors ImportedPackageStore so a future provider surfaces My Designs
 * the same way imported packages are surfaced.
 */
final class UserDesignStore
{
    private const EXTENSION = 'json';

    public function __construct(
        private readonly ?string $basePath = null,
    ) {
    }

    public function directory(): string
    {
        $dir = $this->basePath
            ?? (string) config('page-builder.design_library.my_designs_path', storage_path('app/page-builder/design-library/my-designs'));

        return rtrim($dir, '/\\');
    }

    /**
     * Persist a captured design record, keyed by its (slug) resource id. Atomic.
     *
     * @param  array<string, mixed>  $record
     */
    public function save(string $resourceId, array $record): string
    {
        $dir = $this->directory();
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $path = $this->pathFor($resourceId);
        $json = json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $tmp = $path.'.tmp';
        file_put_contents($tmp, (string) $json, LOCK_EX);
        @rename($tmp, $path);

        // Drop any cached stat for this path so signature() (filemtime + filesize)
        // reflects THIS write immediately — otherwise a rapid rewrite of the same
        // file (e.g. archive then restore in the same second) can read a stale size
        // and fail to invalidate the provider/repository revision cache.
        clearstatcache(true, $path);

        return $path;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        $records = [];
        foreach ($this->files() as $file) {
            $decoded = json_decode((string) file_get_contents($file), true);
            if (is_array($decoded)) {
                $records[] = $decoded;
            }
        }

        return $records;
    }

    /**
     * The first record whose fingerprint matches, or null (duplicate detection).
     *
     * @return array<string, mixed>|null
     */
    public function findByFingerprint(string $fingerprint): ?array
    {
        foreach ($this->all() as $record) {
            if (($record['fingerprint'] ?? null) === $fingerprint) {
                return $record;
            }
        }

        return null;
    }

    /**
     * The record with the given RID, or null. Used by the management lifecycle
     * (Phase 26.49) to load a single design for a metadata / archive / duplicate
     * operation.
     *
     * @return array<string, mixed>|null
     */
    public function findByRid(string $rid): ?array
    {
        foreach ($this->all() as $record) {
            if (($record['rid'] ?? null) === $rid) {
                return $record;
            }
        }

        return null;
    }

    public function has(string $resourceId): bool
    {
        return is_file($this->pathFor($resourceId));
    }

    public function forget(string $resourceId): void
    {
        $path = $this->pathFor($resourceId);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * A cheap content signature so the provider's revision changes with the store.
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
        $glob = glob($this->directory().'/*.'.self::EXTENSION);

        return $glob === false ? [] : $glob;
    }

    private function pathFor(string $resourceId): string
    {
        $slug = preg_replace('/[^a-z0-9._-]+/i', '-', $resourceId);
        $slug = trim((string) $slug, '-');
        if ($slug === '') {
            $slug = 'design';
        }

        return $this->directory().'/'.$slug.'.'.self::EXTENSION;
    }
}
