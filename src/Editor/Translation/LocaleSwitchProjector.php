<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Editor\Translation;

use Plugins\PageBuilder\Authority\ContentAuthority;
use Plugins\PageBuilder\LayoutTranslation\Identity\TranslationGroupKey;
use Plugins\PageBuilder\Support\EditableOwners;
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
 * A locale WITHOUT a Core content translation has no editor URL (opening it would
 * fail closed); instead it carries `can_create` and, when the user may create it,
 * the server-generated `create_url` that creates the translation through Core's
 * content lifecycle before the editor opens it.
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
        private readonly ContentAuthority $authority,
        private readonly SourceLayoutInspector $sources,
    ) {}

    /**
     * @return array<int, array{
     *     locale: string,
     *     label: string,
     *     is_source: bool,
     *     is_current: bool,
     *     has_translation: bool,
     *     has_layout: bool,
     *     editor_url: ?string,
     *     can_create: bool,
     *     create_url: ?string,
     *     source_layout: ?string,
     *     requires_blank_decision: bool
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
        // PB-CORE37-R1 — offer creation only when the actor holds draft authority on
        // THIS content (ownership, managed marker); Core's gate decides the write.
        $canonical = EditableOwners::resolve($canonicalType, $canonicalId);
        $canCreate = EditableOwners::canCreateTranslation($canonicalType)
            && $canonical !== null
            && $this->authority->canEdit($canonical, auth()->user());

        // PB-CORE-AUTHORITY-REMEDIATION-R1 — what a new translated layout would be
        // created from: one source document per group, classified once.
        $sourceLayout = null;

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
                // offer to CREATE it (never to open it directly). Any other fault — a
                // disabled/unknown locale — is not a real member of the group; skip it.
                if ($e->reason !== 'translation_not_found') {
                    continue;
                }
                $hasTranslation = false;
            }

            // PB-CONTENT-AUTHORITY-R3 — offer it only when Core would allow the creation.
            $createUrl = ! $hasTranslation && $canCreate && $this->authority->canCreateTranslation($canonical, auth()->user(), $locale)
                ? $this->createUrl($canonicalType, $canonicalId)
                : null;

            // A translation without a layout is provisioned from the source; with no
            // source content to copy the UI must ask for the explicit blank decision.
            $needsLayout = ! $isSource && ! $hasLayout;
            if ($needsLayout && $sourceLayout === null) {
                $sourceLayout = $this->sources->classify(TranslationGroupKey::forOwner($canonicalType, $canonicalId), $source);
            }

            $projection[] = [
                'locale' => $locale,
                'label' => $this->label($locale),
                'is_source' => $isSource,
                'is_current' => $locale === $current,
                'has_translation' => $hasTranslation,
                'has_layout' => $hasLayout,
                'editor_url' => $hasTranslation ? EditorUrls::live($canonicalType, $canonicalId, null, $locale) : null,
                'can_create' => $createUrl !== null,
                'create_url' => $createUrl,
                'source_layout' => $needsLayout ? $sourceLayout : null,
                'requires_blank_decision' => $needsLayout && $sourceLayout !== SourceLayoutInspector::CLONEABLE,
            ];
        }

        return $projection;
    }

    /**
     * The server-generated translation-create endpoint (null when the route is not
     * registered, e.g. an isolated test boot — the locale is then not creatable).
     */
    private function createUrl(string $canonicalType, int|string $canonicalId): ?string
    {
        try {
            return route('page-builder.api.editor.translations.store', [
                'ownerType' => $canonicalType,
                'ownerId' => $canonicalId,
            ]);
        } catch (\Throwable) {
            return null;
        }
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
