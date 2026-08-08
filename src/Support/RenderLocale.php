<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Support;

use Illuminate\Http\Request;
use TheNguyen\CMS\Localization\Contracts\LanguageConfigurationContract;

/**
 * The Page Builder-owned render-locale authority (PB-STATE-2T).
 *
 * Editor render endpoints resolve the effective OWNER by the editing locale, but
 * a render must also resolve translatable VALUES under that SAME locale —
 * otherwise {@see Localize::text()} reads the ambient admin `app()->getLocale()`
 * and a single component's fields resolve inconsistently (INV-PB-L10N-01/02).
 *
 * This class carries the editing locale from the request (fail-closed against the
 * enabled locales) and SCOPES it around a render, restoring the ambient app
 * locale afterwards so the render locale never leaks past the render. It never
 * resolves owners, persists, publishes, or reads envelope shape — value shape is
 * owned by {@see Localize}; owner identity by the effective-owner resolver.
 */
final class RenderLocale
{
    /**
     * The validated editor locale from a request, or null for the source locale.
     * A supplied-but-disabled locale fails closed (422) so a render can never run
     * under a forged/disabled locale (WORK H). A missing locale is null (ambient).
     */
    public static function fromRequest(Request $request): ?string
    {
        $raw = $request->input('locale');

        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        $locale = self::enabled($raw);
        abort_if($locale === null, 422, 'Locale not enabled.');

        return $locale;
    }

    /**
     * Normalize a locale to its enabled form, or null when it is empty or not an
     * enabled locale. Fail-soft (no abort) for read-only render surfaces that must
     * degrade rather than 4xx. When no language configuration is bound (isolated
     * contexts) the trimmed locale is accepted as-is.
     */
    public static function enabled(?string $locale): ?string
    {
        if ($locale === null || trim($locale) === '') {
            return null;
        }

        $locale = trim($locale);

        if (! app()->bound(LanguageConfigurationContract::class)) {
            return $locale;
        }

        $config = app(LanguageConfigurationContract::class);

        return $config->isEnabled($locale) ? $config->normalize($locale) : null;
    }

    /**
     * Run $render with the app locale scoped to $locale (a no-op when null/empty),
     * always restoring the previous locale — even if the render throws — so the
     * admin request's ambient locale is unchanged afterwards.
     *
     * @template T
     * @param  callable(): T  $render
     * @return T
     */
    public static function scope(?string $locale, callable $render): mixed
    {
        if ($locale === null || $locale === '') {
            return $render();
        }

        $previous = app()->getLocale();
        app()->setLocale($locale);

        try {
            return $render();
        } finally {
            app()->setLocale($previous);
        }
    }
}
