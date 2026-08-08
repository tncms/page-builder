<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Library\Availability;

/**
 * Default capability gate — delegates to the CMS permission system via cms_can().
 *
 * This is the only place the Design Library touches authorization. There is no
 * payment, subscription, or marketplace logic here: a capability is either granted
 * to the current actor or it is not.
 */
final class CmsCapabilityGate implements CapabilityGate
{
    public function allows(string $capability): bool
    {
        return function_exists('cms_can') && cms_can($capability);
    }
}
