<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Support;

use TheNguyen\CMS\Localization\Contracts\PublicLocaleContextContract;
use Throwable;

/**
 * Thin, defensive accessor for the ADMIN UI locale + platform default from Core's read-only
 * {@see PublicLocaleContextContract} (PB-TEMPLATE-REGISTRY-1-H3).
 *
 * The catalog presentation boundary selects human labels by the Admin UI locale — never the
 * editing or frontend locale — so switching the admin interface language relabels the catalog
 * while the editing locale (which value/translation record the editor reads/writes) is
 * untouched. This mirrors {@see FrontendRenderer}'s guarded consumption pattern: in isolated
 * plugin contexts where Core localization is unbound (or throws) it returns null, and the caller
 * then resolves to the resource's invariant metadata. It never sets, persists, or mutates a
 * locale — locale ownership is exclusively Core's.
 */
final class CatalogLocale
{
    /** The current Admin UI locale, or null when Core localization is unavailable. */
    public static function admin(): ?string
    {
        return self::read(static fn (PublicLocaleContextContract $context): string => $context->adminLocale());
    }

    /** The platform default (fallback) locale, or null when Core localization is unavailable. */
    public static function fallback(): ?string
    {
        return self::read(static fn (PublicLocaleContextContract $context): string => $context->default());
    }

    /**
     * @param  callable(PublicLocaleContextContract): string  $reader
     */
    private static function read(callable $reader): ?string
    {
        if (! app()->bound(PublicLocaleContextContract::class)) {
            return null;
        }

        try {
            $value = $reader(app(PublicLocaleContextContract::class));

            return $value === '' ? null : $value;
        } catch (Throwable) {
            return null;
        }
    }
}
