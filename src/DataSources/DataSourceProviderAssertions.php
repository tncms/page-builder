<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\DataSources;

use PHPUnit\Framework\Assert;
use Plugins\PageBuilder\DataSources\Contracts\DataSourceProvider;
use Plugins\PageBuilder\DataSources\Contracts\InternalDataSourceProvider;
use Plugins\PageBuilder\DataSources\Exceptions\NonCompliantDataSourceProviderException;

/**
 * Reusable PHPUnit assertions for Page Builder data-source providers (PB-XREG-2).
 *
 * Drop into any TestCase, or extend {@see DataSourceProviderTestCase}. Registration
 * assertions run against throwaway registries and never touch the live platform;
 * for an external provider the declared namespace is assumed to be its trusted
 * identity (your real plugin satisfies the production rule by living under
 * `plugins/{slug}/`).
 */
trait DataSourceProviderAssertions
{
    protected function assertDataSourceProviderCompliant(DataSourceProvider $provider): DataSourceComplianceReport
    {
        $report = (new DataSourceProviderComplianceValidator)->validate($provider);

        Assert::assertTrue($report->passed(), $report->summary());

        return $report;
    }

    protected function assertDataSourceProviderNotCompliant(DataSourceProvider $provider): DataSourceComplianceReport
    {
        $report = (new DataSourceProviderComplianceValidator)->validate($provider);

        Assert::assertFalse(
            $report->passed(),
            "Data-source provider '{$report->namespace}' was expected to fail compliance but passed.",
        );

        return $report;
    }

    /** Assert the provider registers and every source it declares becomes KNOWN. */
    protected function assertDataSourceProviderRegisters(DataSourceProvider $provider): DataSourceProviderRegistry
    {
        $registry = new DataSourceProviderRegistry(new DataSourceProviderComplianceValidator, new DataSourceProviderIdentity);

        if (! $provider instanceof InternalDataSourceProvider) {
            DataSourceProviderIdentity::fakeIdentityForTesting($provider::class, $provider->namespace());
        }

        $registry->register($provider);

        foreach ($provider->sources() as $definition) {
            Assert::assertSame(
                DataSourceAvailability::KNOWN,
                $registry->availability($definition->id()),
                "registered source '{$definition->id()}' is not resolvable.",
            );
        }

        return $registry;
    }

    /** Assert the registration gate REJECTS a non-compliant provider and leaves the registry empty. */
    protected function assertDataSourceProviderRejected(DataSourceProvider $provider): void
    {
        $registry = new DataSourceProviderRegistry(new DataSourceProviderComplianceValidator, new DataSourceProviderIdentity);

        try {
            $registry->register($provider);
            Assert::fail("Non-compliant data-source provider '{$provider->namespace()}' was registered.");
        } catch (NonCompliantDataSourceProviderException $e) {
            Assert::assertFalse($e->report->passed());
            Assert::assertSame([], $registry->snapshot(), 'A rejected provider must not mutate the registry.');
        }
    }

    /**
     * Assert a fetch result honours the contract bounds: a known status, at most
     * `$limit` records, and plain JSON-serialisable record data within the byte bound.
     */
    protected function assertDataSourceResultIsBounded(DataSourceResult $result, int $limit): void
    {
        Assert::assertContains($result->status, [
            DataSourceResult::STATUS_OK,
            DataSourceResult::STATUS_EMPTY,
            DataSourceResult::STATUS_UNAVAILABLE,
            DataSourceResult::STATUS_ERROR,
        ]);
        Assert::assertLessThanOrEqual(min($limit, DataSourceLimits::MAX_ITEMS), count($result->records), 'result exceeds its limit.');

        $records = $result->toArray()['records'];
        $encoded = json_encode($records);

        Assert::assertIsString($encoded, 'records must be JSON-serialisable.');
        Assert::assertLessThanOrEqual(DataSourceLimits::MAX_RESULT_BYTES, strlen($encoded), 'result exceeds the byte bound.');
        Assert::assertSame($records, json_decode($encoded, true), 'records must be plain data.');
    }
}
