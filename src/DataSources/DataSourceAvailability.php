<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\DataSources;

/**
 * The truthful runtime state of a stored data-source identifier (PB-XREG-2).
 *
 * Same three-state vocabulary as {@see \Plugins\PageBuilder\Components\ComponentAvailability},
 * kept as its own type so the two registries never share state. A stored layout is
 * never rewritten to reflect a state.
 */
final class DataSourceAvailability
{
    /** A registered source owned by an active provider. */
    public const KNOWN = 'known';

    /**
     * A well-formed identifier whose provider is not registered right now
     * (deactivated / uninstalled / not booted). The saved id + config are preserved
     * verbatim and resolve again when the provider returns.
     */
    public const UNAVAILABLE = 'unavailable';

    /** A malformed identifier. */
    public const UNKNOWN = 'unknown';
}
