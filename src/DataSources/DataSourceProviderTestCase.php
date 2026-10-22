<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\DataSources;

use PHPUnit\Framework\TestCase;
use Plugins\PageBuilder\DataSources\Contracts\DataSourceProvider;

/**
 * A drop-in PHPUnit base for certifying a Page Builder data-source provider
 * (PB-XREG-2).
 *
 * Extend it and return your provider from {@see createDataSourceProvider()} to
 * inherit the structural compliance + registration suite. It never calls fetch():
 * exercise your queries in your own (database-backed) tests and assert the
 * outcome with {@see DataSourceProviderAssertions::assertDataSourceResultIsBounded()}.
 *
 * @see docs/extensions/data-source-provider.md
 */
abstract class DataSourceProviderTestCase extends TestCase
{
    use DataSourceProviderAssertions;

    /** Return a fresh instance of the provider under test. */
    abstract protected function createDataSourceProvider(): DataSourceProvider;

    protected function tearDown(): void
    {
        DataSourceProviderIdentity::clearTestingIdentities();

        parent::tearDown();
    }

    public function test_data_source_provider_passes_full_compliance(): void
    {
        $this->assertDataSourceProviderCompliant($this->createDataSourceProvider());
    }

    public function test_data_source_provider_registers_cleanly(): void
    {
        $this->assertDataSourceProviderRegisters($this->createDataSourceProvider());
    }
}
