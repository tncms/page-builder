<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Components\Contracts;

/**
 * The public contract a trusted installed plugin implements to register Page
 * Builder Section and Block component definitions (PB-XREG-1).
 *
 * This is the ONE sanctioned external component-registration seam. A provider is
 * certified by the {@see \Plugins\PageBuilder\Components\ComponentProviderComplianceValidator}
 * and registered through {@see \Plugins\PageBuilder\Components\ComponentRegistration}
 * during the implementing plugin's own service-provider boot, before the registry
 * freezes.
 *
 * A provider supplies only declarative, Page-Builder-owned component definition
 * value objects. It never supplies a callable, controller, service id, Blade path
 * from untrusted data, SQL, URL, or executable manifest — the renderer identity of
 * an external component is a view name in the provider's OWN view namespace,
 * validated server-side (see {@see ExternalComponentDefinition}).
 *
 * @see docs/extensions/component-provider.md
 */
interface ComponentProvider
{
    /**
     * The provider's declared namespace — MUST equal the provider's canonical
     * installed plugin slug. The registration gate derives that slug server-side
     * and rejects the provider when the declared value does not match, so a
     * self-asserted namespace is never trusted.
     */
    public function namespace(): string;

    /**
     * The provider contract / definition schema version this provider targets
     * (SemVer, e.g. "1.0.0"). Lets Page Builder reason about compatibility without
     * inspecting individual definitions.
     */
    public function version(): string;

    /**
     * The component definitions this provider contributes. Every element MUST be a
     * {@see \Plugins\PageBuilder\Contracts\SectionDefinition} or
     * {@see \Plugins\PageBuilder\Contracts\BlockDefinition}, additionally implement
     * {@see ExternalComponentDefinition}, and carry a `namespace:local` identifier
     * whose namespace equals {@see namespace()}.
     *
     * MUST be deterministic: repeated calls yield the same set, same order.
     *
     * @return iterable<int, \Plugins\PageBuilder\Contracts\ComponentDefinition>
     */
    public function components(): iterable;
}
