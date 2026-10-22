<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\DataSources\Contracts;

use Plugins\PageBuilder\DataSources\DataSourceContext;
use Plugins\PageBuilder\DataSources\DataSourceOption;
use Plugins\PageBuilder\DataSources\DataSourceQuery;
use Plugins\PageBuilder\DataSources\DataSourceResult;

/**
 * One bounded, server-executed, read-only record source (PB-XREG-2).
 *
 * Everything except {@see fetch()} is static declarative metadata that Page
 * Builder publishes to the editor and uses to validate every saved configuration.
 * {@see fetch()} runs ONLY server-side, ONLY after Page Builder has validated the
 * configuration against this definition, and MUST:
 *
 *   - be read-only: no writes, locks, counters, stock reservations, cart/order
 *     mutation, business events, mail, jobs, or webhooks;
 *   - honour `$query->limit` (Page Builder also enforces it);
 *   - return only public/visible records (editor previews and public pages share
 *     the same visibility);
 *   - resolve localized values for `$context->locale` through its own canonical
 *     authorities;
 *   - return immutable {@see \Plugins\PageBuilder\DataSources\DataSourceRecord}s,
 *     never ORM models or objects.
 *
 * Exceptions thrown from fetch() are contained by Page Builder and surfaced only
 * as a bounded `source_error` result.
 */
interface DataSourceDefinition
{
    /** Canonical `<provider-slug>:<local-id>` identifier. */
    public function id(): string;

    /** Human label shown in the Inspector (Admin UI locale). */
    public function label(): string;

    /**
     * The kind of record this source yields (a lowercase slug, e.g. `product`).
     * A component field declares which kinds it accepts; only compatible sources
     * are offered and accepted.
     */
    public function recordKind(): string;

    /** Max items this source returns (1..{@see \Plugins\PageBuilder\DataSources\DataSourceLimits::MAX_ITEMS}). */
    public function maxLimit(): int;

    /** Items used when a saved configuration omits a limit (1..maxLimit()). */
    public function defaultLimit(): int;

    /**
     * The fixed, allow-listed options an editor may set (enum / boolean only).
     *
     * @return list<DataSourceOption>
     */
    public function options(): array;

    /** Execute the bounded, read-only query. */
    public function fetch(DataSourceQuery $query, DataSourceContext $context): DataSourceResult;
}
