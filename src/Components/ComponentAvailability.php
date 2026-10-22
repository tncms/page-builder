<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Components;

/**
 * The truthful runtime state of a stored node's component `type` (PB-XREG-1).
 *
 * These three states are kept deliberately distinct so the editor, runtime, and
 * later phases never conflate "the provider is switched off right now" with
 * "this is garbage". A stored layout is never rewritten to reflect a state.
 */
final class ComponentAvailability
{
    /** A registered, resolvable component (built-in, or an active external provider's). */
    public const KNOWN = 'known';

    /**
     * A syntactically valid EXTERNAL identifier whose provider is not registered in
     * the current runtime (uninstalled/disabled/not-yet-booted). The node is
     * preserved verbatim and can resolve again if the provider returns.
     */
    public const UNAVAILABLE = 'unavailable';

    /** A malformed identifier, or a bare id that is not a real built-in. */
    public const UNKNOWN = 'unknown';
}
