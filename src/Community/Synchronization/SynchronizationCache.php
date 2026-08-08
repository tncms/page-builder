<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Community\Synchronization;

/**
 * The local, offline-ready synchronization cache (Phase 26.50C, Rules 7 & 9).
 *
 * For each repository the cache stores exactly three metadata documents — the last
 * synchronized {@see RepositoryIndex} (`index.json`), the synchronization metadata
 * (`meta.json`: last sync time, mode, entry count) and the append-only
 * {@see SynchronizationHistory} (`history.json`) — and NOTHING ELSE. It never stores
 * payload (Rules 7, 16).
 *
 * The cache is plain local files, so it works fully offline: once synchronized, a
 * repository's metadata remains usable with no network (Rule 9). Writes are atomic and
 * Windows-safe (rename-with-fallback), mirroring {@see \Plugins\PageBuilder\Community\CommunityResourceStore}.
 *
 * @see docs/offline-cache.md
 */
final class SynchronizationCache
{
    public function __construct(
        private readonly ?string $basePath = null,
    ) {}

    public function directory(): string
    {
        $dir = $this->basePath
            ?? (string) config('page-builder.design_library.sync_cache_path', storage_path('app/page-builder/design-library/sync-cache'));

        return rtrim($dir, '/\\');
    }

    public function has(string $repository): bool
    {
        return is_file($this->repoDir($repository).'/index.json');
    }

    /**
     * The last synchronized index for a repository, or null when never synchronized.
     */
    public function read(string $repository): ?RepositoryIndex
    {
        $data = $this->readJson($this->repoDir($repository).'/index.json');

        return $data === null ? null : RepositoryIndex::fromArray($data);
    }

    public function write(string $repository, RepositoryIndex $index): void
    {
        $this->writeJson($this->repoDir($repository).'/index.json', $index->toArray());
    }

    /**
     * @return array<string, mixed>
     */
    public function meta(string $repository): array
    {
        return $this->readJson($this->repoDir($repository).'/meta.json') ?? [];
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function writeMeta(string $repository, array $meta): void
    {
        $this->writeJson($this->repoDir($repository).'/meta.json', $meta);
    }

    public function history(string $repository): SynchronizationHistory
    {
        $data = $this->readJson($this->repoDir($repository).'/history.json');

        return SynchronizationHistory::fromArray(is_array($data['entries'] ?? null) ? $data['entries'] : []);
    }

    public function writeHistory(string $repository, SynchronizationHistory $history): void
    {
        $this->writeJson($this->repoDir($repository).'/history.json', ['entries' => $history->toArray()]);
    }

    private function repoDir(string $repository): string
    {
        return $this->directory().'/'.$this->slug($repository);
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
        $dir = dirname($path);
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $json = (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $tmp = $path.'.tmp';
        file_put_contents($tmp, $json, LOCK_EX);

        // POSIX rename is atomic; on Windows rename() does NOT overwrite an existing
        // destination (index/meta/history are rewritten on every sync), so fall back
        // to a direct write.
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

        return $slug === '' ? 'repository' : $slug;
    }
}
