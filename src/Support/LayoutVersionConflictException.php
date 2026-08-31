<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Support;

use RuntimeException;

/**
 * Optimistic-concurrency conflict at the layout persistence boundary
 * (PB-PUBLISH-R1, INV-PUB-06 — Version Safety).
 *
 * Thrown by {@see LayoutStore} inside the locked save/publish transaction when the
 * caller's server-issued version token no longer matches the authoritative persisted
 * state — i.e. a newer draft or published snapshot landed since the caller loaded.
 * Carries the CURRENT authoritative token plus non-secret current-version metadata so
 * the client can reload/reconcile; it never exposes payload contents. The mutation is
 * abandoned (zero writes) before this is thrown.
 */
final class LayoutVersionConflictException extends RuntimeException
{
    /** Stable, namespaced conflict code surfaced to the client. */
    public const ERROR_CODE = 'page_builder.layout.version_conflict';

    /**
     * @param  string  $currentToken  the authoritative token for the owner's current state
     * @param  array<string, mixed>  $current  non-secret current-version metadata (token + state flags), never payloads
     */
    public function __construct(
        public readonly string $currentToken,
        public readonly array $current,
        string $message = 'This layout changed in another session. Reload the latest version before saving or publishing.',
    ) {
        parent::__construct($message);
    }
}
