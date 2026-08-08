<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\ReviewInfrastructure;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Plugins\PageBuilder\LayoutTranslation\Review\Contracts\ReviewSessionStoreInterface;
use Plugins\PageBuilder\LayoutTranslation\Review\Session\ReviewSessionId;
use Plugins\PageBuilder\LayoutTranslation\Review\Session\ReviewSessionSnapshot;

/**
 * Production {@see ReviewSessionStoreInterface}: an EPHEMERAL, cache-backed snapshot store (LT-8D).
 *
 * Layer: Translation (review infrastructure). Holds the payload-safe {@see ReviewSessionSnapshot} in the
 * cache (TTL-bounded), keyed by session id — the authoritative carrier of in-progress decisions between
 * stateless requests (INV-LT-8-PRES-01), reconstructed each action (INV-LT-8A-07). It introduces NO
 * database table, column, or migration — no persistence redesign. A snapshot is enough to reconstruct a
 * session, never enough to reconstruct content.
 */
final class CacheReviewSessionStore implements ReviewSessionStoreInterface
{
    private const PREFIX = 'pb.lt.review-session.';

    public function __construct(
        private readonly CacheRepository $cache,
        private readonly int $ttlSeconds = 3600,
    ) {}

    public function put(ReviewSessionSnapshot $snapshot): void
    {
        $this->cache->put(self::PREFIX.$snapshot->id, $snapshot->toArray(), $this->ttlSeconds);
    }

    public function get(ReviewSessionId $id): ?ReviewSessionSnapshot
    {
        $data = $this->cache->get(self::PREFIX.$id->value);

        return is_array($data) ? ReviewSessionSnapshot::fromArray($data) : null;
    }

    public function forget(ReviewSessionId $id): void
    {
        $this->cache->forget(self::PREFIX.$id->value);
    }
}
