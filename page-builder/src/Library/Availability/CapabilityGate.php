<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Library\Availability;

/**
 * The seam between the Design Library and the CMS capability/entitlement system.
 *
 * It answers ONE question — "does the current actor hold this capability?" — and
 * owns NO billing, subscription, or marketplace logic. FREE and PRO editions share
 * this contract; only the set of granted capabilities differs.
 */
interface CapabilityGate
{
    public function allows(string $capability): bool;
}
