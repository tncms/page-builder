<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Support;

/**
 * Admin-UI-locale display labels for the three built-in Template-Picker templates
 * (PB-FREE-LIBRARY-DESIGN-1-F).
 *
 * The built-in template library is a client-side registry seeded at boot from static
 * TypeScript definitions ({@see \Plugins\PageBuilder\resources\editor\templates\builtin});
 * the editor Vue has no client i18n. This is the SERVER-side localization source for
 * those templates' human labels: it resolves name/description for the ADMIN UI locale
 * (never the editing/frontend locale) and is injected into the editor boot payload, so
 * `registerBuiltinPacks` publishes already-localized library items.
 *
 * English here is the invariant label (kept in step with the TS definition names); the
 * Vietnamese companion lives beside it. Machine identity (template id, layout tree,
 * schema) stays locale-invariant and is owned by the TypeScript definitions — this class
 * only carries the human labels.
 *
 * @phpstan-type LocalizedLabel array{name: string, description: string}
 */
final class BuiltinTemplateCatalog
{
    /**
     * Canonical labels keyed by template id → locale → {name, description}. The `en`
     * entries mirror the TS definition metadata (single source of the English wording).
     *
     * @var array<string, array<string, array{name: string, description: string}>>
     */
    private const LABELS = [
        'hero-basic' => [
            'en' => [
                'name' => 'Basic Hero',
                'description' => 'A headline, subtext, and a primary call to action.',
            ],
            'vi' => [
                'name' => 'Hero cơ bản',
                'description' => 'Một tiêu đề, dòng phụ và một lời kêu gọi hành động chính.',
            ],
        ],
        'feature-grid-basic' => [
            'en' => [
                'name' => 'Basic Feature Grid',
                'description' => 'Three columns of features with headings and short descriptions.',
            ],
            'vi' => [
                'name' => 'Lưới tính năng cơ bản',
                'description' => 'Ba cột tính năng với tiêu đề và mô tả ngắn.',
            ],
        ],
        'cta-basic' => [
            'en' => [
                'name' => 'Basic Call To Action',
                'description' => 'A closing prompt with a single primary action.',
            ],
            'vi' => [
                'name' => 'Kêu gọi hành động cơ bản',
                'description' => 'Một lời nhắc khép lại với một hành động chính duy nhất.',
            ],
        ],
    ];

    /**
     * Resolve every built-in template's labels for one Admin UI locale, with the
     * canonical fallback chain (requested locale → platform default → invariant English).
     * The shape matches what the client applies over the TS definition metadata.
     *
     * @return array<string, array{name: string, description: string}>
     */
    public static function labels(?string $locale, ?string $fallback = null): array
    {
        $out = [];
        foreach (self::LABELS as $id => $byLocale) {
            $out[$id] = [
                'name' => self::pick($byLocale, 'name', $locale, $fallback),
                'description' => self::pick($byLocale, 'description', $locale, $fallback),
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, array{name: string, description: string}>  $byLocale
     */
    private static function pick(array $byLocale, string $field, ?string $locale, ?string $fallback): string
    {
        foreach ([$locale, $fallback, 'en'] as $code) {
            if (! is_string($code) || $code === '') {
                continue;
            }
            $value = $byLocale[strtolower($code)][$field] ?? null;
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return $byLocale['en'][$field] ?? '';
    }
}
