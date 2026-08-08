<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation;

use Plugins\PageBuilder\LayoutTranslation\Contracts\LayoutTranslationCapabilityResolverInterface;

/**
 * The LT-1 capability resolver.
 *
 * LT-1 is a foundation phase: no translation operation (clone, sync, diff, review,
 * translation-scoped preview/publish) is implemented yet, so this implementation
 * advertises no capabilities. Later Builder implementations return a richer static
 * set as each operation lands — callers never branch on concrete classes, and
 * capability never depends on a specific layout's state (that is Eligibility).
 */
final class DefaultLayoutTranslationCapabilityResolver implements LayoutTranslationCapabilityResolverInterface
{
    public function capabilitiesFor(): LayoutTranslationCapabilities
    {
        return LayoutTranslationCapabilities::none();
    }
}
