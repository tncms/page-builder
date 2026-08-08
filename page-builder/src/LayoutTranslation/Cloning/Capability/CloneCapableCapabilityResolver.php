<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Cloning\Capability;

use Plugins\PageBuilder\LayoutTranslation\Contracts\LayoutTranslationCapabilityResolverInterface;
use Plugins\PageBuilder\LayoutTranslation\LayoutTranslationCapabilities;

/**
 * Advertises the capabilities implemented as of LT-3 (clone).
 *
 * Layer: Translation (capability). Implementation-static — it takes no per-layout
 * status (the frozen LT-1.5 boundary is preserved: Capability never depends on
 * Eligibility/state). It reports `supportsClone = true` because LT-3 implements the
 * clone operation; every other capability remains unimplemented. This is the
 * "richer static set as each operation lands" the LT-1 ADR anticipated, delivered
 * as a NEW resolver so the frozen default resolver is untouched.
 */
final class CloneCapableCapabilityResolver implements LayoutTranslationCapabilityResolverInterface
{
    public function capabilitiesFor(): LayoutTranslationCapabilities
    {
        return new LayoutTranslationCapabilities(
            clone: true,
            sync: false,
            diff: false,
            review: false,
            preview: false,
            publish: false,
        );
    }
}
