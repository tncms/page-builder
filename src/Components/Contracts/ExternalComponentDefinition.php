<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Components\Contracts;

use Plugins\PageBuilder\Contracts\ComponentDefinition;

/**
 * A component definition contributed by an external provider (PB-XREG-1).
 *
 * Extends the existing {@see ComponentDefinition} with the ONE extra fact an
 * external component needs that a built-in (resolved by view convention) does not:
 * its trusted server renderer identity.
 *
 * `view()` returns a Blade view name in the PROVIDER'S OWN view namespace — e.g.
 * `travel-booking::components.tour-grid`, which Core maps to the plugin's
 * `resources/views` via `view('{slug}::…')`. The registration gate validates that
 * the view namespace equals the provider's canonical slug, so an external
 * component can never name another plugin's, the theme's, or Page Builder's views,
 * and the persisted namespaced `type` is NEVER turned into a filesystem/view path.
 *
 * The definition's `type()` MUST be the canonical `namespace:local` identifier.
 */
interface ExternalComponentDefinition extends ComponentDefinition
{
    /**
     * The trusted Blade view used to render this component on the public frontend
     * and in the editor preview. MUST be a namespaced view (`{provider-slug}::…`)
     * owned by the provider plugin. Resolved only from this validated registration,
     * never reconstructed from the persisted identifier.
     */
    public function view(): string;
}
