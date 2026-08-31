<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Support;

use Plugins\PageBuilder\Design\TokenCss;
use Plugins\PageBuilder\Support\DynamicStyle\DynamicStyleRegistry;

/**
 * Builds the lightweight PUBLIC frontend head assets for Page Builder content
 * (Phase 23): the design-token `:root` block + the component stylesheet.
 *
 * There is NO JavaScript here — the public frontend never loads Vue, the editor
 * bundle, the iframe bundle, or any toolbar/inspector CSS. Only a single small
 * stylesheet (filemtime cache-busted) plus an inline token block. Because the
 * Live Builder iframe is the same real public document, loading these on the
 * frontend automatically gives the editor identical component styling — one
 * source of truth, no second editor theme.
 */
final class PageBuilderFrontendAssets
{
    /** Marks injected tags so the middleware injects at most once. */
    public const MARKER_ATTR = 'data-pb-frontend-css';

    public const STYLESHEET = 'vendor/page-builder/frontend/page-builder.css';

    public function __construct(
        private readonly TokenCss $tokenCss,
        private readonly ColumnWidthRegistry $columnWidths,
        private readonly DynamicStyleRegistry $dynamicStyles,
    ) {}

    /**
     * The `<style>` token block + component `<link>` for the document head.
     *
     * The token block also carries the per-page runtime styling that the theme's
     * output sanitizer strips from the body (it lives in <head>, outside the
     * sanitised body, so it survives): one `.pb-cw-*{flex…}` rule per arbitrary
     * column width, plus one `.pb-ds-*{…}` rule per registered Dynamic Style
     * declaration (padding / background / alignment / …). Both registries feed a
     * SINGLE `<style>` block and a single injection — one runtime, one head asset.
     */
    public function headHtml(): string
    {
        $href = e(EditorAssets::url(self::STYLESHEET));

        // Content tokens are scoped to the PB rendered root (`.pb-section`), never
        // the global `:root` (PB-FREE-LIBRARY-DESIGN-1-C). Per-page runtime column
        // widths and dynamic styles follow.
        $runtimeCss = $this->tokenCss->renderScoped().$this->columnWidths->cssRules().$this->dynamicStyles->cssRules();

        return '<style '.self::MARKER_ATTR.'>'.$runtimeCss.'</style>'
            .'<link rel="stylesheet" '.self::MARKER_ATTR.' href="'.$href.'">';
    }
}
