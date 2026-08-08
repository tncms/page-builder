<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Support;

use TheNguyen\CMS\Localization\Contracts\LanguageConfigurationContract;
use Throwable;

/**
 * Resolve a possibly-translatable field value to a plain string for rendering —
 * the single Page Builder read boundary for the canonical translatable-field
 * representation (ADR-PB-TRANSLATABLE-FIELD-REPRESENTATION, PB-STATE-2T).
 *
 * Canonical representation is a per-locale map (`{ "vi": "…", "en": "…" }`); a
 * plain string is a LEGACY value interpreted as the source-locale text. Server-
 * side block/section views must never receive the raw array — echoing it crashes
 * htmlspecialchars() (or prints "Array") — so this helper flattens the map to the
 * requested locale for Blade's {{ }}.
 *
 * Resolution is DETERMINISTIC and policy-driven (INV-PB-L10N-05), never based on
 * array key order:
 *   1. the requested locale's value (an explicit "" is a real empty translation);
 *   2. else the approved source (default) locale's value — never a hardcoded
 *      locale, never the first map entry;
 *   3. else empty (the field's schema default is already applied upstream).
 * A legacy scalar is returned verbatim in every locale (it IS the source text),
 * so old payloads and the source owner keep rendering. The render locale itself
 * is supplied by the caller (public URL prefix / {@see RenderLocale} scope), so
 * editor and public surfaces resolve the same payload identically.
 */
final class Localize
{
    public static function text(mixed $value, ?string $locale = null): string
    {
        if (is_array($value)) {
            $locale ??= app()->getLocale();

            // 1) Requested locale — an explicit value (including "") is a real translation.
            if (array_key_exists($locale, $value) && is_string($value[$locale])) {
                return $value[$locale];
            }

            // 2) Deterministic fallback to the approved source/default locale.
            $source = self::sourceLocale();
            if ($source !== null && $source !== $locale
                && array_key_exists($source, $value) && is_string($value[$source])) {
                return $value[$source];
            }

            // 3) Missing — empty. Never guess by array order.
            return '';
        }

        if ($value === null) {
            return '';
        }

        return is_string($value) ? $value : (is_scalar($value) ? (string) $value : '');
    }

    /**
     * The approved source (default) locale from Core's public language
     * configuration, or null when it is unavailable (isolated plugin contexts) —
     * in which case there is no source fallback and a missing locale renders empty.
     * Never hardcodes a locale.
     */
    private static function sourceLocale(): ?string
    {
        if (! app()->bound(LanguageConfigurationContract::class)) {
            return null;
        }

        try {
            return app(LanguageConfigurationContract::class)->defaultLocale();
        } catch (Throwable) {
            return null;
        }
    }
}
