<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Contracts;

use Plugins\PageBuilder\LayoutTranslation\LayoutTranslationCapabilities;

/**
 * Advertises the capabilities a Builder implementation supports (LT-1, locked).
 *
 * Capability is architectural metadata that is INDEPENDENT of any specific
 * layout's status: it answers only "what is this implementation capable of?",
 * never "is this layout eligible?", "may this actor?", or "should this workflow
 * run?". Those are the Eligibility, Authorization and Workflow layers, resolved
 * elsewhere. The resolver therefore takes no per-descriptor state — a different
 * Builder implementation advertises a different static set.
 */
interface LayoutTranslationCapabilityResolverInterface
{
    public function capabilitiesFor(): LayoutTranslationCapabilities;
}
