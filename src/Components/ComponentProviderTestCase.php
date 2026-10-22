<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Components;

use PHPUnit\Framework\TestCase;
use Plugins\PageBuilder\Components\Contracts\ComponentProvider;

/**
 * A drop-in PHPUnit base for certifying a Page Builder component provider
 * (PB-XREG-1).
 *
 * Extend it, return your provider from {@see createComponentProvider()}, and
 * inherit the compliance suite the built-in library itself passes — no platform
 * bootstrapping required; the base runs against throwaway registries only.
 *
 * @see docs/extensions/testing-and-packaging.md
 */
abstract class ComponentProviderTestCase extends TestCase
{
    use ComponentProviderAssertions;

    /** Return a fresh instance of the provider under test. */
    abstract protected function createComponentProvider(): ComponentProvider;

    protected function tearDown(): void
    {
        ComponentProviderIdentity::clearTestingIdentities();

        parent::tearDown();
    }

    public function test_provider_passes_full_compliance(): void
    {
        $this->assertComponentProviderCompliant($this->createComponentProvider());
    }

    public function test_provider_registers_cleanly(): void
    {
        $this->assertComponentProviderRegisters($this->createComponentProvider());
    }
}
