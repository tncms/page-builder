<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Preview;

use Closure;

/**
 * In-memory (per-request) cache of rendered previews.
 *
 * Preview rendering is deterministic, so identical contexts can safely reuse a
 * previously-built PreviewResponse within the same request/process. This cache
 * introduces NO persistent storage — it is a plain in-process map that lives and
 * dies with the container instance (registered as a singleton).
 */
final class PreviewCache
{
    /** @var array<string, PreviewResponse> */
    private array $store = [];

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->store);
    }

    public function get(string $key): ?PreviewResponse
    {
        return $this->store[$key] ?? null;
    }

    public function put(string $key, PreviewResponse $response): PreviewResponse
    {
        $this->store[$key] = $response;

        return $response;
    }

    /**
     * @param  Closure(): PreviewResponse  $factory
     */
    public function remember(string $key, Closure $factory): PreviewResponse
    {
        if ($this->has($key)) {
            return $this->store[$key];
        }

        return $this->put($key, $factory());
    }

    public function forget(string $key): void
    {
        unset($this->store[$key]);
    }

    public function flush(): void
    {
        $this->store = [];
    }
}
