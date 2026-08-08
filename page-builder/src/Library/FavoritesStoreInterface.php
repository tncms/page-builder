<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Library;

/**
 * Local favorites for the Design Library.
 *
 * Favorites are a LOCAL concern only — no accounts, no cloud sync. The default
 * implementation is session-scoped ({@see SessionFavoritesStore}); swapping in an
 * account- or cloud-backed store later is a pure implementation change (this
 * interface is the seam). The browser additionally mirrors favorites in
 * localStorage for instant UX.
 */
interface FavoritesStoreInterface
{
    /**
     * @return array<int, string> RIDs, deterministically ordered
     */
    public function all(): array;

    public function has(string $rid): bool;

    public function add(string $rid): void;

    public function remove(string $rid): void;

    /**
     * @return bool the new favorite state (true = now a favorite)
     */
    public function toggle(string $rid): bool;
}
