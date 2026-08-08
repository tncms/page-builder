<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Editor\Translation;

use Plugins\PageBuilder\Support\EditorUrls;
use Plugins\PageBuilder\Support\LayoutStore;
use TheNguyen\CMS\Localization\Contracts\LanguageConfigurationContract;

/**
 * Server-side "available locales" projection for the in-editor language switcher
 * (LT-M1B Increment 2).
 *
 * The switcher must be data-driven from the server — the browser never computes
 * an owner, layout id, or translation id. For a canonical page this builds, for
 * every ENABLED locale, a read-only descriptor: its code + native label, whether
 * it is the source/default locale, whether it is the one currently open, whether
 * a Core content translation exists, whether a Page Builder layout has been
 * provisioned yet, and the server-generated locale-aware editor URL.
 *
 * It reuses the ONE effective-owner resolver ({@see EffectiveEditorOwnerResolver})
 * — never a second resolution path — and is pure: no cloning, no writes.
 */
final class LocaleSwitchProjector
{
    public function __construct(
        private readonly LanguageConfigurationContract $languages,
        private readonly EffectiveEditorOwnerResolver $owners,
        private readonly LayoutStore $layouts,
    ) {}

    /**
     * @return array<int, array{
     *     locale: string,
     *     label: string,
     *     is_source: bool,
     *     is_current: bool,
     *     has_translation: bool,
     *     has_layout: bool,
     *     editor_url: ?string
     * }>
     */
    public function forOwner(string $canonicalType, int|string $canonicalId, string $currentLocale): array
    {
        // Feature dormant (LT-M1B Inc2 §11): no in-editor switcher. Every locale
        // resolves the single shared layout, so there is nothing to switch between.
        if (! (bool) config('page-builder.translation.enabled', false)) {
            return [];
        }

        $source = $this->languages->normalize($this->languages->defaultLocale());
        $current = $this->languages->normalize($currentLocale);

        $projection = [];

        foreach ($this->languages->enabledLocales() as $raw) {
            $locale = $this->languages->normalize($raw);
            $isSource = $locale === $source;

            $hasTranslation = $isSource;
            $hasLayout = false;

            try {
                // Same resolver the editor uses: source keeps the canonical owner,
                // a non-default locale derives the content_translation owner.
                $effective = $this->owners->resolve($canonicalType, $canonicalId, $locale);
                $hasTranslation = true;
                $hasLayout = $this->layouts->find($effective->ownerType, $effective->ownerId) !== null;
            } catch (EditorOwnerResolutionException $e) {
                // No Core content translation yet: still list the locale so the UI can
                // offer it (opening provisions on first open). Any other fault — a
                // disabled/unknown locale — is not a real member of the group; skip it.
                if ($e->reason !== 'translation_not_found') {
                    continue;
                }
                $hasTranslation = false;
            }

            $projection[] = [
                'locale' => $locale,
                'label' => $this->label($locale),
                'is_source' => $isSource,
                'is_current' => $locale === $current,
                'has_translation' => $hasTranslation,
                'has_layout' => $hasLayout,
                'editor_url' => EditorUrls::live($canonicalType, $canonicalId, null, $locale),
            ];
        }

        return $projection;
    }

    /**
     * The human, native label for a locale (falls back to the upper-cased code
     * when Core exposes no metadata for it).
     */
    private function label(string $locale): string
    {
        $metadata = $this->languages->metadata($locale);

        if ($metadata === null) {
            return strtoupper($locale);
        }

        return $metadata->nativeName !== '' ? $metadata->nativeName : $metadata->name;
    }
}
