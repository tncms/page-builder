<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Support;

use Plugins\PageBuilder\Filament\Pages\PageBuilderEditorPage;
use Plugins\PageBuilder\Filament\Pages\PageBuilderFrontendEditorPage;
use TheNguyen\CMS\Models\Content;

/**
 * Centralised Page Builder navigation URLs (Phase 22.2).
 *
 * The Structure editor, Live Builder shell, the Page/Post launcher panel and the
 * Blade boot payloads all need the same four links (structure / live / public /
 * classic). Keeping the route knowledge here means no editor route string is
 * duplicated across PHP, Blade and Vue. The Filament page routes are resolved
 * through their own `getUrl()` and guarded — an unregistered route (e.g. in an
 * isolated test where the plugin is inactive) yields null rather than throwing,
 * and the caller simply hides that link.
 */
final class EditorUrls
{
    /** The reserved Structure editor URL for an owner (null when unavailable). */
    public static function structure(string $ownerType, int|string $ownerId, ?string $selected = null, ?string $locale = null): ?string
    {
        return self::withLocale(
            self::withSelection(
                self::pageUrl(PageBuilderEditorPage::class, $ownerType, $ownerId),
                $selected,
            ),
            $locale,
        );
    }

    /** The full-screen Live Builder URL for an owner (null when unavailable). */
    public static function live(string $ownerType, int|string $ownerId, ?string $selected = null, ?string $locale = null): ?string
    {
        return self::withLocale(
            self::withSelection(
                self::pageUrl(PageBuilderFrontendEditorPage::class, $ownerType, $ownerId),
                $selected,
            ),
            $locale,
        );
    }

    /**
     * The owner's real public (frontend) URL, in $locale when given.
     *
     * A localized editor (LT-M1) must open the owner's REAL localized page so its
     * URL prefix drives Core's public locale for the WHOLE document (theme, page
     * title, header/menu, language control) — not just the page-builder canvas,
     * which reads `?locale=`. Falls back to the canonical URL when the locale has
     * no public URL yet (content_url yields its '#' sentinel), so the link is
     * never dead.
     */
    public static function publicUrl(Content $owner, ?string $locale = null): string
    {
        if (! function_exists('content_url')) {
            return url('/');
        }

        if ($locale !== null && $locale !== '') {
            $localized = content_url($owner, $locale);

            if ($localized !== '' && $localized !== '#') {
                return $localized;
            }
        }

        return content_url($owner);
    }

    /**
     * The PRIVATE, authenticated editor-document URL for the frontend editor iframe
     * (PB-DRAFT-PREVIEW-R1), in $locale when given.
     *
     * Identity-based (ownerType/ownerId) and private: unlike {@see publicUrl()} it
     * does NOT depend on the owner's public slug being routable, so the editor canvas
     * loads the persisted draft even while the Core content entity is unpublished. The
     * route is guarded (`page-builder.edit` + locale validation) inside its controller;
     * an unregistered route (isolated test where the plugin is inactive) yields null so
     * the caller can fall back. This is the iframe document URL — NOT the public page:
     * {@see publicUrl()} stays the authority for the "View Page" action.
     */
    public static function document(string $ownerType, int|string $ownerId, ?string $locale = null): ?string
    {
        try {
            $url = route('page-builder.editor-document', [
                'ownerType' => $ownerType,
                'ownerId' => (string) $ownerId,
            ]);
        } catch (\Throwable) {
            return null;
        }

        return self::withLocale($url, $locale);
    }

    /** The owner's classic admin edit URL (null when the resource is absent). */
    public static function classic(string $ownerType, Content $owner): ?string
    {
        return EditableOwners::editUrl($ownerType, $owner);
    }

    /**
     * All four links for an owner, as a plain array for the Blade boot payload.
     *
     * @return array{structure: ?string, live: ?string, public: string, classic: ?string}
     */
    public static function all(string $ownerType, Content $owner, ?string $selected = null, ?string $locale = null): array
    {
        $ownerId = (string) $owner->getKey();

        return [
            'structure' => self::structure($ownerType, $ownerId, $selected, $locale),
            'live' => self::live($ownerType, $ownerId, $selected, $locale),
            'public' => self::publicUrl($owner, $locale),
            'classic' => self::classic($ownerType, $owner),
        ];
    }

    /**
     * @param  class-string  $page
     */
    /**
     * Append `?selected=<id>` (or `&selected=`) to a URL, preserving any existing
     * query. Public so both the PHP callers and tests share one implementation
     * (mirrors the JS `appendSelection`). A null url / empty id is a no-op.
     */
    public static function withSelection(?string $url, ?string $selected): ?string
    {
        if ($url === null || $selected === null || $selected === '') {
            return $url;
        }

        $sep = str_contains($url, '?') ? '&' : '?';

        return $url.$sep.'selected='.rawurlencode($selected);
    }

    /**
     * Append `?locale=<code>` (or `&locale=`) to an editor URL, preserving any
     * existing query (LT-M1B Increment 2). The locale is editor context, not layout
     * identity — the backend re-resolves the effective owner from the canonical page
     * + this locale on every request. A null url / empty locale is a no-op.
     */
    public static function withLocale(?string $url, ?string $locale): ?string
    {
        if ($url === null || $locale === null || $locale === '') {
            return $url;
        }

        $sep = str_contains($url, '?') ? '&' : '?';

        return $url.$sep.'locale='.rawurlencode($locale);
    }

    private static function pageUrl(string $page, string $ownerType, int|string $ownerId): ?string
    {
        try {
            /** @phpstan-ignore-next-line — both pages expose getUrl() (Filament Page). */
            return $page::getUrl(['ownerType' => $ownerType, 'ownerId' => (string) $ownerId]);
        } catch (\Throwable) {
            return null;
        }
    }
}
