<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Support;

/**
 * Per-request collector of the column widths used while rendering a public page
 * (column-width parity hotfix, 2026-07).
 *
 * WHY THIS EXISTS: the public content body is passed through the theme's output
 * sanitizer (`cms_html()` → HtmlSanitizer), whose global allow-list is `['class']`
 * only — it strips every inline `style` attribute (and HTML comments). The Page
 * Builder's self-contained inline column geometry (`flex:0 0 30%`) therefore never
 * reaches the anonymous frontend, so exact-percentage columns collapse to 100%
 * and wrap. The Live Builder iframe is never sanitized, which is why it looked
 * correct.
 *
 * Classes survive sanitisation, and the component `<style>`/`<link>` the middleware
 * injects into <head> is OUTSIDE the sanitised body, so it survives too. So each
 * percentage column emits a stable width CLASS ({@see classFor}) and registers its
 * basis here; {@see \Plugins\PageBuilder\Support\PageBuilderFrontendAssets} then
 * emits one `.pb-cw-*{flex…}` rule per used width into the head block. Fixed
 * aliases (half/third/quarter/full) keep their static bundle classes.
 *
 * Registered as a singleton; {@see reset} is called once per public render so the
 * set reflects only the current page (safe under Octane / test reuse).
 */
final class ColumnWidthRegistry
{
    /** @var array<string, string> canonical basis ("30%") => class ("pb-cw-30") */
    private array $used = [];

    /** Discard everything collected so far (call once before a public render). */
    public function reset(): void
    {
        $this->used = [];
    }

    /**
     * Register a safe percentage basis and return its stable, CSS-safe class —
     * or null when the basis is not a plain percentage (nothing is emitted, so
     * no unsafe value can reach the stylesheet).
     */
    public function classFor(string $basis): ?string
    {
        if (! preg_match('/^\d+(?:\.\d+)?%$/', $basis)) {
            return null;
        }

        // "30%" => "pb-cw-30", "33.3333%" => "pb-cw-33-3333".
        $class = 'pb-cw-'.str_replace(['%', '.'], ['', '-'], $basis);
        $this->used[$basis] = $class;

        return $class;
    }

    /** Whether any width has been registered this request. */
    public function isEmpty(): bool
    {
        return $this->used === [];
    }

    /**
     * One `.pb-cw-*` flex rule per registered width, safe to embed in a `<style>`
     * block (values are validated percentages). Empty string when nothing used.
     */
    public function cssRules(): string
    {
        $css = '';

        foreach ($this->used as $basis => $class) {
            $css .= '.'.$class.'{flex:0 0 '.$basis.';max-width:'.$basis.';}';
        }

        return $css;
    }
}
