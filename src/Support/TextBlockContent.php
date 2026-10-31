<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Support;

use TheNguyen\CMS\Localization\Contracts\LanguageConfigurationContract;
use Throwable;

/**
 * Which value a `text` block renders in a locale (PB-TEXT-AUTHORING-R1).
 *
 * The persisted `text` type is unchanged. Its legacy `text` value is plain text,
 * echoed escaped exactly as before. The visual editor writes rich HTML to a separate
 * translatable `content` key, only for the locale actually edited; `text` is never
 * rewritten. Per locale (same deterministic policy as {@see Localize}):
 *
 *   1. an explicit `content` value for the locale → rich (including "", a real empty);
 *   2. else an explicit `text` value for the locale → plain;
 *   3. a legacy scalar `text` → plain in every locale (it IS the source text);
 *   4. else the source (default) locale's `content`, then its `text`;
 *   5. else empty plain text.
 *
 * So an untouched layout renders byte-for-byte as before, and editing one locale
 * never changes what another locale renders.
 */
final class TextBlockContent
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{rich: bool, value: string}
     */
    public static function resolve(array $data, ?string $locale = null): array
    {
        $locale ??= app()->getLocale();
        $content = $data['content'] ?? null;
        $text = $data['text'] ?? null;

        if (is_array($content) && is_string($content[$locale] ?? null)) {
            return ['rich' => true, 'value' => $content[$locale]];
        }

        if (is_array($text) && is_string($text[$locale] ?? null)) {
            return ['rich' => false, 'value' => $text[$locale]];
        }

        if (is_scalar($text)) {
            return ['rich' => false, 'value' => (string) $text];
        }

        $source = self::sourceLocale();

        if ($source !== null && $source !== $locale) {
            if (is_array($content) && is_string($content[$source] ?? null)) {
                return ['rich' => true, 'value' => $content[$source]];
            }

            if (is_array($text) && is_string($text[$source] ?? null)) {
                return ['rich' => false, 'value' => $text[$source]];
            }
        }

        return ['rich' => false, 'value' => ''];
    }

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
