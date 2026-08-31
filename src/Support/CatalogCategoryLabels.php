<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Support;

/**
 * Admin-UI-locale display labels for the catalog category KEYS shared by the Resource
 * Browser and the Pattern Picker (PB-FREE-LIBRARY-DESIGN-1-F).
 *
 * Category is a stable, locale-invariant machine key on every resource (`hero`,
 * `social-proof`, …); this class turns it into a human label for the current Admin UI
 * locale so the catalog filters never show a raw slug. Unknown keys degrade to a
 * title-cased form of the key itself (never an empty string), so a third-party category
 * still renders a readable label.
 */
final class CatalogCategoryLabels
{
    /**
     * key → locale → label. English mirrors the product vocabulary; Vietnamese uses the
     * established TN CMS Page Builder terms.
     *
     * @var array<string, array{en: string, vi: string}>
     */
    private const LABELS = [
        // Page categories
        'business' => ['en' => 'Business', 'vi' => 'Doanh nghiệp'],
        'agency' => ['en' => 'Agency', 'vi' => 'Agency'],
        'saas' => ['en' => 'SaaS', 'vi' => 'SaaS'],
        'portfolio' => ['en' => 'Portfolio', 'vi' => 'Portfolio'],
        'blog' => ['en' => 'Blog', 'vi' => 'Blog'],
        // Section / pattern categories
        'hero' => ['en' => 'Hero', 'vi' => 'Hero'],
        'features' => ['en' => 'Features', 'vi' => 'Tính năng'],
        'social-proof' => ['en' => 'Social proof', 'vi' => 'Bằng chứng xã hội'],
        'cta' => ['en' => 'CTA', 'vi' => 'Kêu gọi hành động'],
        'conversion' => ['en' => 'Conversion', 'vi' => 'Chuyển đổi'],
        'content' => ['en' => 'Content', 'vi' => 'Nội dung'],
        'faq' => ['en' => 'FAQ', 'vi' => 'Câu hỏi thường gặp'],
        'footer' => ['en' => 'Footer', 'vi' => 'Chân trang'],
        'gallery' => ['en' => 'Gallery', 'vi' => 'Thư viện ảnh'],
        'logos' => ['en' => 'Logos', 'vi' => 'Logo'],
        'testimonials' => ['en' => 'Testimonials', 'vi' => 'Nhận xét'],
        'utility' => ['en' => 'Utility', 'vi' => 'Tiện ích'],
    ];

    /**
     * The full key → label map for one Admin UI locale (all known categories). The client
     * looks a category key up here; a key absent from the map falls back client-side to
     * the raw key, and {@see label()} provides the same safe default server-side.
     *
     * @return array<string, string>
     */
    public static function map(?string $locale, ?string $fallback = null): array
    {
        $out = [];
        foreach (self::LABELS as $key => $_) {
            $out[$key] = self::label($key, $locale, $fallback);
        }

        return $out;
    }

    public static function label(string $key, ?string $locale, ?string $fallback = null): string
    {
        $entry = self::LABELS[$key] ?? null;
        if ($entry === null) {
            return self::titleCase($key);
        }

        foreach ([$locale, $fallback, 'en'] as $code) {
            if (! is_string($code) || $code === '') {
                continue;
            }
            $value = $entry[strtolower($code)] ?? null;
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return $entry['en'];
    }

    /** Human title-case for an unknown slug key (e.g. `my-category` → `My category`). */
    private static function titleCase(string $key): string
    {
        $words = preg_split('/[-_.]+/', $key) ?: [$key];
        $first = ucfirst(strtolower((string) ($words[0] ?? $key)));
        $rest = array_map(static fn (string $w): string => strtolower($w), array_slice($words, 1));

        return trim($first.' '.implode(' ', $rest));
    }
}
