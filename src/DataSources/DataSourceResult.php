<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\DataSources;

use InvalidArgumentException;

/**
 * The immutable outcome of one data-source execution (PB-XREG-2).
 *
 * Four explicit states, so a view never has to guess:
 *
 *   - `ok`          — one or more records;
 *   - `empty`       — the source ran and matched nothing;
 *   - `unavailable` — the configured source's provider is not registered right now
 *                     (the saved configuration is preserved; nothing else is tried);
 *   - `error`       — invalid configuration, a provider failure, or an out-of-bounds
 *                     result, described ONLY by a bounded machine `code`.
 *
 * Never carries exception messages, traces, SQL, paths, or class names.
 */
final class DataSourceResult
{
    public const STATUS_OK = 'ok';

    public const STATUS_EMPTY = 'empty';

    public const STATUS_UNAVAILABLE = 'unavailable';

    public const STATUS_ERROR = 'error';

    /** Bounded error / state codes. */
    public const CODE_NOT_CONFIGURED = 'not_configured';

    public const CODE_SOURCE_UNAVAILABLE = 'source_unavailable';

    public const CODE_INVALID_CONFIG = 'invalid_config';

    public const CODE_INCOMPATIBLE_SOURCE = 'incompatible_source';

    public const CODE_SOURCE_ERROR = 'source_error';

    public const CODE_INVALID_RESULT = 'invalid_result';

    public const CODE_RESULT_TOO_LARGE = 'result_too_large';

    private const CODES = [
        self::CODE_NOT_CONFIGURED,
        self::CODE_SOURCE_UNAVAILABLE,
        self::CODE_INVALID_CONFIG,
        self::CODE_INCOMPATIBLE_SOURCE,
        self::CODE_SOURCE_ERROR,
        self::CODE_INVALID_RESULT,
        self::CODE_RESULT_TOO_LARGE,
    ];

    /**
     * @param  list<DataSourceRecord>  $records
     */
    private function __construct(
        public readonly string $status,
        public readonly array $records,
        public readonly ?string $code,
    ) {}

    /**
     * @param  iterable<DataSourceRecord>  $records
     */
    public static function ok(iterable $records): self
    {
        $list = [];
        foreach ($records as $record) {
            if (! $record instanceof DataSourceRecord) {
                throw new InvalidArgumentException('Data-source results may only contain DataSourceRecord instances.');
            }
            $list[] = $record;
        }

        return $list === [] ? self::empty() : new self(self::STATUS_OK, $list, null);
    }

    public static function empty(): self
    {
        return new self(self::STATUS_EMPTY, [], null);
    }

    public static function unavailable(): self
    {
        return new self(self::STATUS_UNAVAILABLE, [], self::CODE_SOURCE_UNAVAILABLE);
    }

    public static function notConfigured(): self
    {
        return new self(self::STATUS_EMPTY, [], self::CODE_NOT_CONFIGURED);
    }

    public static function error(string $code): self
    {
        return new self(
            self::STATUS_ERROR,
            [],
            in_array($code, self::CODES, true) ? $code : self::CODE_SOURCE_ERROR,
        );
    }

    public function isOk(): bool
    {
        return $this->status === self::STATUS_OK;
    }

    /** A copy keeping at most `$limit` records (the executor's hard ceiling). */
    public function limitedTo(int $limit): self
    {
        if (count($this->records) <= $limit) {
            return $this;
        }

        return new self($this->status, array_slice($this->records, 0, max(0, $limit)), $this->code);
    }

    /**
     * The plain array a trusted component view receives.
     *
     * @return array{status: string, code: ?string, records: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'code' => $this->code,
            'records' => array_map(static fn (DataSourceRecord $r): array => $r->toArray(), $this->records),
        ];
    }
}
