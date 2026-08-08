<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\SDK;

use PHPUnit\Framework\Assert;
use Plugins\PageBuilder\Contracts\DesignResourceProvider;
use Plugins\PageBuilder\Repository\DesignResourceRepository;
use Plugins\PageBuilder\Repository\ResourceQuery;
use Plugins\PageBuilder\SDK\Exceptions\NonCompliantProviderException;

/**
 * Reusable PHPUnit assertions for Design Resource providers (Phase 26.49B, Rule 7).
 *
 * A third-party provider is testable with the SAME compliance assertions the
 * shipped providers pass — drop this trait into any TestCase, or extend
 * {@see ProviderTestCase}. The assertions run the provider against a throwaway
 * repository, so they never touch the live platform.
 */
trait ProviderAssertions
{
    /**
     * Assert a provider passes every compliance check and return its report.
     */
    protected function assertProviderCompliant(DesignResourceProvider $provider): ComplianceReport
    {
        $report = (new ProviderComplianceValidator)->validate($provider);

        Assert::assertTrue($report->passed(), $report->summary());

        return $report;
    }

    /**
     * Assert a provider FAILS compliance — useful for negative fixtures.
     */
    protected function assertProviderNotCompliant(DesignResourceProvider $provider): ComplianceReport
    {
        $report = (new ProviderComplianceValidator)->validate($provider);

        Assert::assertFalse($report->passed(), "Provider '{$report->providerKey}' was expected to fail compliance but passed.");

        return $report;
    }

    /**
     * Assert find() round-trips every resource all() yields.
     */
    protected function assertProviderFindMatchesAll(DesignResourceProvider $provider): void
    {
        foreach ($provider->all() as $resource) {
            $found = $provider->find($resource->rid);
            Assert::assertNotNull($found, "find('{$resource->rid}') returned null.");
            Assert::assertSame($resource->rid, $found->rid);
        }
    }

    /**
     * Assert the provider's resources are queryable & searchable through the repository.
     */
    protected function assertProviderSupportsSearch(DesignResourceProvider $provider): void
    {
        $repository = new DesignResourceRepository;
        $repository->registerProvider($provider);

        foreach ($provider->all() as $resource) {
            $hits = $repository->query(ResourceQuery::make()->search($resource->name))->rids();
            Assert::assertContains(
                $resource->rid,
                $hits,
                "search('{$resource->name}') did not surface '{$resource->rid}'.",
            );

            return; // one representative resource is enough to prove the seam.
        }
    }

    /**
     * Assert compliance-gated registration accepts a compliant provider and that
     * its resources become resolvable through the repository.
     */
    protected function assertProviderRegisters(DesignResourceProvider $provider): void
    {
        $repository = new DesignResourceRepository;
        (new ProviderRegistration)->register($repository, $provider);

        foreach ($provider->all() as $resource) {
            Assert::assertTrue($repository->has($resource->rid), "registered resource '{$resource->rid}' is not resolvable.");
        }
    }

    /**
     * Assert compliance-gated registration REJECTS a non-compliant provider and
     * leaves the repository untouched.
     */
    protected function assertProviderRejected(DesignResourceProvider $provider): void
    {
        $repository = new DesignResourceRepository;

        try {
            (new ProviderRegistration)->register($repository, $provider);
            Assert::fail("Non-compliant provider '{$provider->key()}' was registered.");
        } catch (NonCompliantProviderException $e) {
            Assert::assertFalse($e->report->passed());
            Assert::assertTrue($repository->all()->isEmpty(), 'A rejected provider must not mutate the repository.');
        }
    }
}
