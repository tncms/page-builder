<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\DataSources;

/**
 * The Page-Builder-owned hard bounds for the Data Source contract (PB-XREG-2).
 *
 * One place, so the validator, the config gate, the executor, the SDK assertions,
 * and the docs all quote the same numbers. A provider may declare tighter limits
 * (e.g. its own max items) but never looser ones.
 */
final class DataSourceLimits
{
    /** Global ceiling on items any source may return for one render. */
    public const MAX_ITEMS = 24;

    /** Max sources a single provider may register. */
    public const MAX_SOURCES_PER_PROVIDER = 32;

    /** Max declared options per source. */
    public const MAX_OPTIONS = 8;

    /** Max choices per enum option. */
    public const MAX_CHOICES = 16;

    /** Max characters for a human label (source, option, choice). */
    public const MAX_LABEL_LENGTH = 120;

    /** Option keys and record field keys: lowercase snake identifiers. */
    public const KEY_PATTERN = '/^[a-z][a-z0-9_]{0,31}$/';

    /** Max fields on one record. */
    public const MAX_RECORD_FIELDS = 24;

    /** Max characters for one string value inside a record. */
    public const MAX_RECORD_STRING_LENGTH = 2048;

    /** Max items in a scalar list inside a record. */
    public const MAX_RECORD_LIST_ITEMS = 16;

    /** Max JSON bytes of one whole result's records. */
    public const MAX_RESULT_BYTES = 131072;

    /** Max JSON bytes of one saved `{source, limit, options}` config value. */
    public const MAX_CONFIG_BYTES = 2048;

    /** Max record kinds one data-source field may accept. */
    public const MAX_ACCEPTED_KINDS = 8;
}
