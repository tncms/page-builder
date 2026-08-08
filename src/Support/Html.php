<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Support;

/**
 * Tiny HTML/URL safety helpers for the server-side block views (Phase 4).
 *
 * Blade's {{ }} already HTML-escapes text, but it does NOT neutralise dangerous
 * URL schemes (e.g. `javascript:`) in href/src attributes. `safeUrl()` closes
 * that gap so authored link/image fields can never smuggle script execution
 * into the rendered page.
 */
final class Html
{
    /** URL schemes considered safe for href/src output. */
    private const ALLOWED_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    /**
     * Return a URL that is safe to place in an href/src attribute, or the
     * fallback when the value is empty or uses a disallowed scheme.
     *
     * Relative paths, anchors and query-only URLs are passed through unchanged;
     * absolute URLs must use an allowed scheme. The result is still expected to
     * be echoed through {{ }} for HTML-attribute escaping.
     */
    public static function safeUrl(mixed $url, string $fallback = '#'): string
    {
        if (! is_string($url)) {
            return $fallback;
        }

        $url = trim($url);

        if ($url === '') {
            return $fallback;
        }

        // Relative path, in-page anchor, or query string: no scheme to vet.
        if (str_starts_with($url, '/') || str_starts_with($url, '#') || str_starts_with($url, '?')) {
            return $url;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);

        // No scheme parsed => treat as a relative reference and allow it.
        if (! is_string($scheme) || $scheme === '') {
            return $url;
        }

        return in_array(strtolower($scheme), self::ALLOWED_SCHEMES, true) ? $url : $fallback;
    }
}
