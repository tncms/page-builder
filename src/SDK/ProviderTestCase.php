<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\SDK;

use PHPUnit\Framework\TestCase;
use Plugins\PageBuilder\Contracts\DesignResourceProvider;

/**
 * A drop-in PHPUnit base for certifying a Design Resource provider (Phase 26.49B,
 * Rule 7).
 *
 * Extend it, return your provider from {@see createProvider()}, and inherit the
 * full compliance suite — the SAME checks the shipped providers pass. Third-party
 * channels (Community, Marketplace, PRO, Portable Package) are certified with
 * identical tests, no platform bootstrapping required: the base runs the provider
 * against throwaway repositories only.
 *
 * @see docs/provider-testing.md
 */
abstract class ProviderTestCase extends TestCase
{
    use ProviderAssertions;

    /**
     * Return a fresh instance of the provider under test.
     */
    abstract protected function createProvider(): DesignResourceProvider;

    public function test_provider_passes_full_compliance(): void
    {
        $this->assertProviderCompliant($this->createProvider());
    }

    public function test_provider_find_round_trips_all(): void
    {
        $this->assertProviderFindMatchesAll($this->createProvider());
    }

    public function test_provider_resources_are_searchable(): void
    {
        $this->assertProviderSupportsSearch($this->createProvider());
    }

    public function test_provider_registers_with_a_repository(): void
    {
        $this->assertProviderRegisters($this->createProvider());
    }
}
