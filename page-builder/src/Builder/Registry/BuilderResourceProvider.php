<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Builder\Registry;

use Plugins\PageBuilder\Repository\DesignResource;

/**
 * A contributor to the single {@see BuilderResourceRegistry} (Phase 26.47E).
 *
 * Providers map Design Resources into a palette GROUP (Built-in, Imported, …).
 * They never scan files and never invent resources: every resource a provider
 * yields originates from the authoritative DesignResourceRepository (Rule 3).
 *
 * This is the extensibility seam (Rule 14): a third-party plugin registers its
 * own provider to add a group without modifying Builder code. My Designs /
 * Marketplace / PRO are reserved future providers (Rule 15) — only this interface
 * is reserved for them, nothing is implemented.
 */
interface BuilderResourceProvider
{
    /** Stable, unique provider key (dedup + idempotent registration). */
    public function key(): string;

    /** The palette group key this provider contributes to (see PaletteGroup::GROUP_*). */
    public function group(): string;

    /** Human label for the group in the palette. */
    public function groupLabel(): string;

    /** Ordering across groups — LOWER appears first. */
    public function priority(): int;

    /** Cheap token that changes when the contributed resource set changes (cache key). */
    public function revision(): int;

    /**
     * The Design Resources this provider contributes, pulled from the repository.
     *
     * @return iterable<int, DesignResource>
     */
    public function resources(): iterable;
}
