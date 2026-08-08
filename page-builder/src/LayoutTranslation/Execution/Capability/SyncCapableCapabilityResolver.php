<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Execution\Capability;

use Plugins\PageBuilder\LayoutTranslation\Contracts\LayoutTranslationCapabilityResolverInterface;
use Plugins\PageBuilder\LayoutTranslation\LayoutTranslationCapabilities;

/**
 * Advertises the capabilities implemented as of LT-7 (clone + diff + sync).
 *
 * Layer: Translation (capability). Implementation-static — it takes no per-layout status (the frozen
 * LT-1.5 boundary holds: Capability never depends on Eligibility/state). LT-5 delivered the structural
 * diff and LT-7 delivered synchronization execution, so `diff` and `sync` are now advertised alongside
 * the LT-3 `clone`. Review/preview/publish remain unimplemented. Delivered as a NEW resolver so the
 * frozen default and LT-3 clone resolvers are untouched — the container binding simply advances to this
 * one. Actual execution stays gated by `page-builder.translation.enabled`.
 */
final class SyncCapableCapabilityResolver implements LayoutTranslationCapabilityResolverInterface
{
    public function capabilitiesFor(): LayoutTranslationCapabilities
    {
        return new LayoutTranslationCapabilities(
            clone: true,
            sync: true,
            diff: true,
            review: false,
            preview: false,
            publish: false,
        );
    }
}
