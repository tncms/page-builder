<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\SDK;

use Plugins\PageBuilder\Contracts\DesignResourceProvider;
use Plugins\PageBuilder\Contracts\RepositoryContract;
use Plugins\PageBuilder\SDK\Exceptions\NonCompliantProviderException;

/**
 * The official registration helper for Design Resource providers (Phase 26.49B,
 * Rules 1 & 3).
 *
 * Registration is compliance-gated: a provider is certified by the
 * {@see ProviderComplianceValidator} FIRST, and only a passing provider is handed
 * to the repository. A non-compliant provider is refused with a
 * {@see NonCompliantProviderException} and never reaches the index — so an invalid
 * channel cannot corrupt the platform.
 *
 * The helper only ever calls the ONE public extension point,
 * {@see RepositoryContract::registerProvider()}. It does not — and cannot — replace
 * the Repository, the Registry, the Capture Pipeline or Runtime Preview; those are
 * not registration targets (Rule 10).
 *
 * @see docs/provider-lifecycle.md
 */
final class ProviderRegistration
{
    public function __construct(
        private readonly ProviderComplianceValidator $validator = new ProviderComplianceValidator,
    ) {}

    /**
     * Certify and register a provider with the repository.
     *
     * @throws NonCompliantProviderException when the provider fails compliance
     */
    public function register(RepositoryContract $repository, DesignResourceProvider $provider): ComplianceReport
    {
        $report = $this->validator->validate($provider);

        if (! $report->passed()) {
            throw new NonCompliantProviderException($report);
        }

        $repository->registerProvider($provider);

        return $report;
    }

    /**
     * Certify and register a provider only if it is compliant; returns the report
     * either way. Use when a soft, non-throwing registration is preferred.
     */
    public function registerIfCompliant(RepositoryContract $repository, DesignResourceProvider $provider): ComplianceReport
    {
        $report = $this->validator->validate($provider);

        if ($report->passed()) {
            $repository->registerProvider($provider);
        }

        return $report;
    }
}
