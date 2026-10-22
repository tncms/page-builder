<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Components;

use Plugins\PageBuilder\Components\Contracts\ComponentProvider;
use Plugins\PageBuilder\Components\Exceptions\ComponentNamespaceViolationException;
use Plugins\PageBuilder\Components\Exceptions\ComponentRegistryFrozenException;
use Plugins\PageBuilder\Components\Exceptions\DuplicateComponentIdentifierException;
use Plugins\PageBuilder\Components\Exceptions\NonCompliantComponentProviderException;

/**
 * The official, documented entry point a plugin uses to register a
 * {@see ComponentProvider} with Page Builder (PB-XREG-1).
 *
 * Deliberately mirrors the Resource Provider SDK's ProviderRegistration so the two
 * developer surfaces feel identical. Registration is always compliance-gated and
 * identity-checked by the {@see ComponentProviderRegistry}; this helper just makes
 * the call ergonomic and offers a soft, non-throwing variant.
 *
 * @see docs/extensions/component-provider.md
 */
final class ComponentRegistration
{
    /**
     * Register a provider, throwing on any failure (non-compliance, namespace
     * violation, duplicate identifier, or a frozen registry).
     */
    public function register(ComponentProviderRegistry $registry, ComponentProvider $provider): ComponentComplianceReport
    {
        return $registry->register($provider);
    }

    /**
     * Register a provider only if it can be registered cleanly; never throws.
     * Returns a report describing the outcome — either the passing report, or a
     * report with an added failed `registration` check explaining why it was
     * refused.
     */
    public function registerIfCompliant(ComponentProviderRegistry $registry, ComponentProvider $provider): ComponentComplianceReport
    {
        try {
            return $registry->register($provider);
        } catch (NonCompliantComponentProviderException $e) {
            return $e->report;
        } catch (ComponentNamespaceViolationException|DuplicateComponentIdentifierException|ComponentRegistryFrozenException $e) {
            return $this->withRegistrationFailure($provider, $e->getMessage());
        }
    }

    private function withRegistrationFailure(ComponentProvider $provider, string $message): ComponentComplianceReport
    {
        $namespace = '';
        try {
            $namespace = trim($provider->namespace());
        } catch (\Throwable) {
            // leave blank
        }

        return new ComponentComplianceReport($namespace, [
            ComponentComplianceCheck::fail('registration', $message),
        ]);
    }
}
