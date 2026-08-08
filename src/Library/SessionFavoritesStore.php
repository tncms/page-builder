<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Library;

use Illuminate\Contracts\Session\Session;

/**
 * Session-scoped local favorites (the default {@see FavoritesStoreInterface}).
 *
 * Local per browser session, requires no account, and stores nothing beyond a
 * list of RIDs. RIDs are kept sorted so listings are deterministic.
 */
final class SessionFavoritesStore implements FavoritesStoreInterface
{
    private const KEY = 'page-builder.design-library.favorites';

    public function __construct(private readonly Session $session)
    {
    }

    public function all(): array
    {
        $stored = $this->session->get(self::KEY, []);
        $rids = is_array($stored) ? array_values(array_filter($stored, 'is_string')) : [];
        sort($rids);

        return $rids;
    }

    public function has(string $rid): bool
    {
        return in_array($rid, $this->all(), true);
    }

    public function add(string $rid): void
    {
        $rids = $this->all();
        if (! in_array($rid, $rids, true)) {
            $rids[] = $rid;
            $this->put($rids);
        }
    }

    public function remove(string $rid): void
    {
        $this->put(array_values(array_filter($this->all(), static fn (string $r): bool => $r !== $rid)));
    }

    public function toggle(string $rid): bool
    {
        if ($this->has($rid)) {
            $this->remove($rid);

            return false;
        }

        $this->add($rid);

        return true;
    }

    /**
     * @param  array<int, string>  $rids
     */
    private function put(array $rids): void
    {
        sort($rids);
        $this->session->put(self::KEY, $rids);
    }
}
