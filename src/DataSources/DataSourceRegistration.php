<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\DataSources;

use Plugins\PageBuilder\DataSources\Contracts\DataSourceProvider;
use Plugins\PageBuilder\DataSources\Exceptions\DataSourceNamespaceViolationException;
use Plugins\PageBuilder\DataSources\Exceptions\DataSourceRegistryFrozenException;
use Plugins\PageBuilder\DataSources\Exceptions\DuplicateDataSourceIdentifierException;
use Plugins\PageBuilder\DataSources\Exceptions\NonCompliantDataSourceProviderException;

/**
 * The official, documented entry point a plugin uses to register a
 * {@see DataSourceProvider} with Page Builder (PB-XREG-2). Mirrors
 * {@see \Plugins\PageBuilder\Components\ComponentRegistration}.
 *
 * @see docs/extensions/data-source-provider.md
 */
final class DataSourceRegistration
{
    /** Register a provider, throwing on any failure. */
    public function register(DataSourceProviderRegistry $registry, DataSourceProvider $provider): DataSourceComplianceReport
    {
        return $registry->register($provider);
    }

    /**
     * Register a provider only if it can be registered cleanly; never throws. One
     * broken provider therefore never prevents other providers from registering.
     */
    public function registerIfCompliant(DataSourceProviderRegistry $registry, DataSourceProvider $provider): DataSourceComplianceReport
    {
        try {
            return $registry->register($provider);
        } catch (NonCompliantDataSourceProviderException $e) {
            return $e->report;
        } catch (DataSourceNamespaceViolationException|DuplicateDataSourceIdentifierException|DataSourceRegistryFrozenException $e) {
            $namespace = '';
            try {
                $namespace = trim($provider->namespace());
            } catch (\Throwable) {
                // leave blank
            }

            return new DataSourceComplianceReport($namespace, [
                DataSourceComplianceCheck::fail('registration', $e->getMessage()),
            ]);
        }
    }
}
