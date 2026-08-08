<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Runtime;

use Plugins\PageBuilder\Contracts\ComponentInstance;

/**
 * Decides whether a component instance is visible — Phase 7 always returns true.
 *
 * The seam exists so later phases can evaluate visibility conditions (device,
 * auth state, roles, …); today every component is visible, so nothing is hidden
 * and the output matches the pre-runtime renderer.
 */
class ConditionResolver
{
    public function resolve(ComponentInstance $instance): bool
    {
        return true;
    }
}
