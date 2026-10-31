<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Interactive\Contracts;

use Plugins\PageBuilder\Components\Contracts\ExternalComponentDefinition;
use Plugins\PageBuilder\Interactive\InteractiveBehavior;

/**
 * An external component that hydrates with provider-owned client code (PB-XREG-3, Interactive Runtime 1.0.0).
 *
 * Purely additive to the frozen Component Provider 1.0.0 contract: a provider opts in by implementing this
 * interface on an {@see ExternalComponentDefinition}. The behavior is validated inside the same atomic
 * registration as the component, its assets resolve only from the provider's own published namespace, and
 * nothing about it is ever stored in a layout. The trusted view must render a complete, usable static
 * presentation — the behavior is progressive enhancement only.
 */
interface InteractiveComponentDefinition extends ExternalComponentDefinition
{
    /** Must be deterministic and side-effect free; it is read at registration time only. */
    public function behavior(): InteractiveBehavior;
}
