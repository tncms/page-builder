<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation;

use Plugins\PageBuilder\LayoutTranslation\Contracts\LayoutTranslationCapabilityResolverInterface;
use Plugins\PageBuilder\LayoutTranslation\Contracts\LayoutTranslationStateReaderInterface;
use Plugins\PageBuilder\LayoutTranslation\Identity\LayoutKey;
use Plugins\PageBuilder\LayoutTranslation\Identity\PageTranslationKey;
use Plugins\PageBuilder\LayoutTranslation\Identity\TranslationGroupKey;
use Plugins\PageBuilder\Support\EditableOwners;
use TheNguyen\CMS\Localization\Contracts\LanguageConfigurationContract;

/**
 * Resolves the canonical {@see LayoutTranslationDescriptor} for a page/post
 * translation (LT-1, locked).
 *
 * It resolves the relationship — Translation Group → Page Translation → Layout —
 * into an immutable descriptor, never a storage entity. Deterministic, read-only,
 * side-effect free: it never clones, never creates a fallback, never writes, never
 * persists. Locale authority is CONSUMED from Core
 * ({@see LanguageConfigurationContract}); the plugin never owns it. Identity keys
 * project the Core owner identity; the multi-dimensional status comes from the
 * minimal state-reader seam and capabilities from the capability resolver — both
 * behind the descriptor boundary.
 *
 * When the feature is disabled every locale maps onto the single legacy shared
 * layout (the source locale's layout), so a single-language site is unchanged.
 */
final class LayoutTranslationResolver
{
    public function __construct(
        private readonly LayoutTranslationConfig $config,
        private readonly LanguageConfigurationContract $languages,
        private readonly LayoutTranslationStateReaderInterface $states,
        private readonly LayoutTranslationCapabilityResolverInterface $capabilities,
    ) {}

    public function resolve(string $ownerType, int|string $ownerId, ?string $locale = null): LayoutTranslationDescriptor
    {
        $source = $this->languages->normalize($this->languages->defaultLocale());
        $enabled = $this->config->enabled();

        // Disabled: legacy behaviour — the single shared layout is the source
        // locale's layout, resolved for every request.
        $code = $enabled ? $this->languages->normalize($locale ?? $source) : $source;
        $isSource = $code === $source;

        $group = TranslationGroupKey::forOwner($ownerType, $ownerId);
        $translation = PageTranslationKey::forGroup($group, $code);
        $layout = LayoutKey::forTranslation($translation);

        if (! EditableOwners::isSupported($ownerType)) {
            return LayoutTranslationDescriptor::unresolved(
                $group, $translation, $layout, $source, $isSource,
                LayoutTranslationResolutionFault::UnsupportedOwnerType, LayoutTranslationCapabilities::none(),
            );
        }

        if ($enabled && ! $this->languages->isEnabled($code)) {
            return LayoutTranslationDescriptor::unresolved(
                $group, $translation, $layout, $source, $isSource,
                LayoutTranslationResolutionFault::LocaleNotEnabled, LayoutTranslationCapabilities::none(),
            );
        }

        $status = $this->states->statusFor($ownerType, $ownerId, $code, $isSource);

        // Capability is implementation-static (independent of this layout's status
        // and the feature flag — those are Eligibility/Availability, not Capability).
        $capabilities = $this->capabilities->capabilitiesFor();

        return LayoutTranslationDescriptor::resolved(
            $group, $translation, $layout, $source, $isSource, $status, $capabilities,
        );
    }
}
