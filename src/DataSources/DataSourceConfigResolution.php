<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\DataSources;

use Plugins\PageBuilder\DataSources\Contracts\DataSourceDefinition;

/**
 * The outcome of validating one saved `{source, limit, options}` value against the
 * registry (PB-XREG-2). Produced only by {@see DataSourceConfigValidator}.
 *
 *   - `valid`         — a KNOWN, compatible source plus a bounded query;
 *   - `empty`         — no source chosen yet (nothing to execute);
 *   - `unavailable`   — well-formed, but the source's provider is not registered
 *                       (preserve verbatim, never substitute another source);
 *   - `invalid`       — malformed, or rejected by the KNOWN source's declaration;
 *                       `reason` is a bounded machine code.
 */
final class DataSourceConfigResolution
{
    public const STATE_VALID = 'valid';

    public const STATE_EMPTY = 'empty';

    public const STATE_UNAVAILABLE = 'unavailable';

    public const STATE_INVALID = 'invalid';

    private function __construct(
        public readonly string $state,
        public readonly ?string $sourceId = null,
        public readonly ?DataSourceDefinition $definition = null,
        public readonly ?DataSourceQuery $query = null,
        public readonly ?string $reason = null,
    ) {}

    public static function valid(string $sourceId, DataSourceDefinition $definition, DataSourceQuery $query): self
    {
        return new self(self::STATE_VALID, $sourceId, $definition, $query);
    }

    public static function empty(): self
    {
        return new self(self::STATE_EMPTY);
    }

    public static function unavailable(string $sourceId): self
    {
        return new self(self::STATE_UNAVAILABLE, $sourceId);
    }

    public static function invalid(string $reason, ?string $sourceId = null): self
    {
        return new self(self::STATE_INVALID, $sourceId, null, null, $reason);
    }

    public function isValid(): bool
    {
        return $this->state === self::STATE_VALID;
    }

    public function isInvalid(): bool
    {
        return $this->state === self::STATE_INVALID;
    }
}
