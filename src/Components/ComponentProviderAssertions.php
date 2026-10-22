<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Components;

use PHPUnit\Framework\Assert;
use Plugins\PageBuilder\Components\Contracts\ComponentProvider;
use Plugins\PageBuilder\Components\Contracts\InternalComponentProvider;
use Plugins\PageBuilder\Components\Exceptions\NonCompliantComponentProviderException;
use Plugins\PageBuilder\Registries\BlockRegistry;
use Plugins\PageBuilder\Registries\SectionRegistry;

/**
 * Reusable PHPUnit assertions for Page Builder component providers (PB-XREG-1).
 *
 * A third-party provider is certified with the SAME assertions the built-in
 * library passes — drop this trait into any TestCase, or extend
 * {@see ComponentProviderTestCase}. The assertions run against throwaway
 * registries and never touch the live platform. For external providers the
 * provider's own class→slug identity is assumed trusted (faked to the declared
 * namespace), so authors certify shape/compliance without the production
 * plugin-slug derivation.
 */
trait ComponentProviderAssertions
{
    /** Assert a provider passes every compliance check; returns its report. */
    protected function assertComponentProviderCompliant(ComponentProvider $provider): ComponentComplianceReport
    {
        $report = (new ComponentProviderComplianceValidator)->validate($provider);

        Assert::assertTrue($report->passed(), $report->summary());

        return $report;
    }

    /** Assert a provider FAILS compliance — useful for negative fixtures. */
    protected function assertComponentProviderNotCompliant(ComponentProvider $provider): ComponentComplianceReport
    {
        $report = (new ComponentProviderComplianceValidator)->validate($provider);

        Assert::assertFalse(
            $report->passed(),
            "Component provider '{$report->namespace}' was expected to fail compliance but passed.",
        );

        return $report;
    }

    /**
     * Assert the provider registers cleanly and every component it contributes is
     * resolvable through the registry's backing stores.
     */
    protected function assertComponentProviderRegisters(ComponentProvider $provider): void
    {
        $sections = new SectionRegistry;
        $blocks = new BlockRegistry;
        $registry = new ComponentProviderRegistry(
            $sections,
            $blocks,
            new ComponentProviderComplianceValidator,
            new ComponentProviderIdentity,
        );

        if (! $provider instanceof InternalComponentProvider) {
            ComponentProviderIdentity::fakeIdentityForTesting($provider::class, $provider->namespace());
        }

        $registry->register($provider);

        foreach ($provider->components() as $definition) {
            $id = $definition->type();
            Assert::assertTrue(
                $sections->has($id) || $blocks->has($id),
                "registered component '{$id}' is not resolvable.",
            );
            Assert::assertSame(ComponentAvailability::KNOWN, $registry->availability($id));
        }
    }

    /**
     * Assert the registration gate REJECTS a non-compliant provider and leaves the
     * registry untouched.
     */
    protected function assertComponentProviderRejected(ComponentProvider $provider): void
    {
        $sections = new SectionRegistry;
        $blocks = new BlockRegistry;
        $registry = new ComponentProviderRegistry(
            $sections,
            $blocks,
            new ComponentProviderComplianceValidator,
            new ComponentProviderIdentity,
        );

        try {
            $registry->register($provider);
            Assert::fail("Non-compliant provider '{$provider->namespace()}' was registered.");
        } catch (NonCompliantComponentProviderException $e) {
            Assert::assertFalse($e->report->passed());
            Assert::assertSame(0, $sections->count() + $blocks->count(), 'A rejected provider must not mutate the registry.');
        }
    }
}
