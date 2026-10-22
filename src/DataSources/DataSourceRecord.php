<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\DataSources;

use Plugins\PageBuilder\DataSources\Exceptions\InvalidDataSourceRecordException;

/**
 * One immutable, bounded, plain-data record returned by a source (PB-XREG-2).
 *
 * The ONLY shape a trusted component view ever receives from a provider: a flat
 * map of snake_case keys to scalars/null, or short lists of scalars. No models,
 * objects, closures, or nested structures can be constructed, so nothing
 * executable or lazily-loading reaches a view or a cache.
 */
final class DataSourceRecord
{
    /**
     * @param  array<string, scalar|null|list<scalar|null>>  $fields
     */
    private function __construct(public readonly array $fields) {}

    /**
     * @param  array<mixed>  $fields
     *
     * @throws InvalidDataSourceRecordException
     */
    public static function make(array $fields): self
    {
        if (count($fields) > DataSourceLimits::MAX_RECORD_FIELDS) {
            throw InvalidDataSourceRecordException::because('too many fields.');
        }

        foreach ($fields as $key => $value) {
            if (! is_string($key) || preg_match(DataSourceLimits::KEY_PATTERN, $key) !== 1) {
                throw InvalidDataSourceRecordException::because('field keys must be snake_case identifiers.');
            }

            if (is_array($value)) {
                if (! array_is_list($value) || count($value) > DataSourceLimits::MAX_RECORD_LIST_ITEMS) {
                    throw InvalidDataSourceRecordException::because('list "'.$key.'" must be a short list.');
                }

                foreach ($value as $item) {
                    self::assertScalar($key, $item);
                }

                continue;
            }

            self::assertScalar($key, $value);
        }

        return new self($fields);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->fields[$key] ?? $default;
    }

    /** @return array<string, scalar|null|list<scalar|null>> */
    public function toArray(): array
    {
        return $this->fields;
    }

    private static function assertScalar(string $key, mixed $value): void
    {
        if ($value !== null && ! is_scalar($value)) {
            throw InvalidDataSourceRecordException::because('field "'.$key.'" must be plain scalar data.');
        }

        if (is_float($value) && ! is_finite($value)) {
            throw InvalidDataSourceRecordException::because('field "'.$key.'" must be a finite number.');
        }

        if (is_string($value) && mb_strlen($value) > DataSourceLimits::MAX_RECORD_STRING_LENGTH) {
            throw InvalidDataSourceRecordException::because('field "'.$key.'" is too long.');
        }
    }
}
