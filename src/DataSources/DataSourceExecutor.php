<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\DataSources;

use Illuminate\Support\Facades\Log;
use Plugins\PageBuilder\DataSources\Exceptions\InvalidDataSourceRecordException;
use Throwable;

/**
 * Executes one validated data-source configuration with containment (PB-XREG-2).
 *
 * The only caller of {@see Contracts\DataSourceDefinition::fetch()}. It:
 *
 *   - never executes anything that is not a `valid` resolution;
 *   - never falls back to a different source when the configured one is
 *     unavailable or failing;
 *   - contains every provider exception as a bounded `source_error` (the log entry
 *     carries the source id and exception class only — never the message, which
 *     may contain SQL, paths, or secrets);
 *   - enforces the hard item ceiling and the result byte bound.
 *
 * There is NO hard wall-clock timeout: PHP offers no safe per-call preemption in
 * this runtime. Bounds are cooperative (item limit, byte limit, provider-owned
 * bounded queries) and documented as such.
 */
final class DataSourceExecutor
{
    public function execute(DataSourceConfigResolution $resolution, DataSourceContext $context): DataSourceResult
    {
        return match ($resolution->state) {
            DataSourceConfigResolution::STATE_EMPTY => DataSourceResult::notConfigured(),
            DataSourceConfigResolution::STATE_UNAVAILABLE => DataSourceResult::unavailable(),
            DataSourceConfigResolution::STATE_INVALID => DataSourceResult::error(
                $resolution->reason === DataSourceConfigValidator::REASON_INCOMPATIBLE_SOURCE
                    ? DataSourceResult::CODE_INCOMPATIBLE_SOURCE
                    : DataSourceResult::CODE_INVALID_CONFIG
            ),
            default => $this->fetch($resolution, $context),
        };
    }

    private function fetch(DataSourceConfigResolution $resolution, DataSourceContext $context): DataSourceResult
    {
        $definition = $resolution->definition;
        $query = $resolution->query;

        if ($definition === null || $query === null) {
            return DataSourceResult::error(DataSourceResult::CODE_INVALID_CONFIG);
        }

        try {
            $result = $definition->fetch($query, $context);
        } catch (InvalidDataSourceRecordException $e) {
            $this->report($resolution->sourceId, $e);

            return DataSourceResult::error(DataSourceResult::CODE_INVALID_RESULT);
        } catch (Throwable $e) {
            $this->report($resolution->sourceId, $e);

            return DataSourceResult::error(DataSourceResult::CODE_SOURCE_ERROR);
        }

        if (! $result instanceof DataSourceResult) {
            return DataSourceResult::error(DataSourceResult::CODE_INVALID_RESULT);
        }

        $result = $result->limitedTo(min($query->limit, DataSourceLimits::MAX_ITEMS));

        $encoded = json_encode($result->toArray()['records']);
        if ($encoded === false || strlen($encoded) > DataSourceLimits::MAX_RESULT_BYTES) {
            return DataSourceResult::error(DataSourceResult::CODE_RESULT_TOO_LARGE);
        }

        return $result;
    }

    private function report(?string $sourceId, Throwable $e): void
    {
        try {
            Log::warning('Page Builder data source failed.', [
                'source' => $sourceId,
                'exception' => $e::class,
            ]);
        } catch (Throwable) {
            // Logging must never break rendering.
        }
    }
}
