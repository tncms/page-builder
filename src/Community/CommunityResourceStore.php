<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Community;

/**
 * Local, file-based storage for the Community Repository (Phase 26.50A, Rule 4).
 *
 * Each resource is a directory holding exactly the repository's storable artifacts —
 * `manifest.json`, `payload.json` and an optional `preview.json` — and NOTHING else
 * (no Builder runtime). The store is platform-neutral and offline-ready: the same
 * layout is what a Portable Package, an Offline Bundle or an Enterprise Mirror
 * places on disk, so no changes are needed to consume them (Rule 14).
 *
 * This is STORAGE, not distribution: there is no network, no download, no sync and
 * no publishing workflow (Rule 18). Writing a resource here simply materialises the
 * canonical files locally.
 */
final class CommunityResourceStore
{
    public const SOURCE = 'community';

    public function __construct(
        private readonly ?string $basePath = null,
    ) {}

    public function directory(): string
    {
        $dir = $this->basePath
            ?? (string) config('page-builder.design_library.community_path', storage_path('app/page-builder/design-library/community'));

        return rtrim($dir, '/\\');
    }

    /**
     * Every stored resource as its raw artifacts (manifest + payload + preview).
     *
     * @return array<int, array{manifest: array<string, mixed>, payload: array<string, mixed>, preview: array<string, mixed>, path: string}>
     */
    public function all(): array
    {
        $records = [];
        foreach ($this->resourceDirs() as $dir) {
            $manifest = $this->readJson($dir.'/manifest.json');
            if ($manifest === null) {
                continue; // a directory without a manifest is not a resource
            }

            $records[] = [
                'manifest' => $manifest,
                'payload' => $this->readJson($dir.'/payload.json') ?? [],
                'preview' => $this->readJson($dir.'/preview.json') ?? [],
                // Phase 26.50B — lifecycle status / publishing history live beside the
                // manifest; absent means "externally provided / published" by convention.
                'lifecycle' => $this->readJson($dir.'/lifecycle.json') ?? [],
                'slug' => basename($dir),
                'path' => $dir,
            ];
        }

        return $records;
    }

    /**
     * The stored record whose manifest RID matches, or null.
     *
     * @return array{manifest: array<string, mixed>, payload: array<string, mixed>, preview: array<string, mixed>, lifecycle: array<string, mixed>, slug: string, path: string}|null
     */
    public function find(string $rid): ?array
    {
        foreach ($this->all() as $record) {
            if (($record['manifest']['rid'] ?? null) === $rid) {
                return $record;
            }
        }

        return null;
    }

    /**
     * Persist a resource's lifecycle document (status + publishing history). Storage
     * only — never distribution.
     *
     * @param  array<string, mixed>  $lifecycle
     */
    public function writeLifecycle(string $slug, array $lifecycle): void
    {
        $dir = $this->directory().'/'.$this->slug($slug);
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $this->writeJson($dir.'/lifecycle.json', $lifecycle);
    }

    /**
     * Write a resource's artifacts to local storage. Storage only — never a
     * publishing workflow. Returns the resource directory.
     *
     * @param  array<string, mixed>  $manifest
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $preview
     */
    public function write(string $slug, array $manifest, array $payload, array $preview = []): string
    {
        $dir = $this->directory().'/'.$this->slug($slug);
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $this->writeJson($dir.'/manifest.json', $manifest);
        $this->writeJson($dir.'/payload.json', $payload);
        if ($preview !== []) {
            $this->writeJson($dir.'/preview.json', $preview);
        }

        return $dir;
    }

    /**
     * A cheap content signature so the provider's revision changes with the store.
     */
    public function signature(): string
    {
        $parts = [];
        foreach ($this->resourceDirs() as $dir) {
            foreach (['manifest.json', 'payload.json', 'preview.json', 'lifecycle.json'] as $file) {
                $path = $dir.'/'.$file;
                if (is_file($path)) {
                    $parts[] = $path.':'.(string) @filemtime($path).':'.(string) @filesize($path);
                }
            }
        }
        sort($parts);

        return $parts === [] ? '' : hash('crc32b', implode('|', $parts));
    }

    /**
     * @return array<int, string>
     */
    private function resourceDirs(): array
    {
        $base = $this->directory();
        if (! is_dir($base)) {
            return [];
        }

        $glob = glob($base.'/*', GLOB_ONLYDIR);

        return $glob === false ? [] : $glob;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readJson(string $path): ?array
    {
        if (! is_file($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function writeJson(string $path, array $data): void
    {
        $json = (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $tmp = $path.'.tmp';
        file_put_contents($tmp, $json, LOCK_EX);

        // POSIX rename is atomic; on Windows rename() does NOT overwrite an existing
        // destination and would silently leave stale content (lifecycle.json is
        // rewritten on every workflow step), so fall back to a direct write.
        if (! @rename($tmp, $path)) {
            file_put_contents($path, $json, LOCK_EX);
            @unlink($tmp);
        }
        clearstatcache(true, $path);
    }

    private function slug(string $value): string
    {
        $slug = preg_replace('/[^a-z0-9._-]+/i', '-', $value);
        $slug = trim((string) $slug, '-');

        return $slug === '' ? 'resource' : $slug;
    }
}
