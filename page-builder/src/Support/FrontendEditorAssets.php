<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Support;

/**
 * Builds the TRUSTED frontend-editor document assets — the iframe runtime
 * `<link>` (head) and boot object + `<script>` (footer) — for injection through
 * a document seam that BYPASSES `cms_html()`.
 *
 * These tags used to be concatenated into `cms.content.body`, where the theme's
 * `{!! cms_html($body) !!}` stripped both `<script>` and `<link>` outright (the
 * sanitizer only keeps a small element/attribute allow-list). That is why the
 * iframe editor runtime was completely absent from the final document. This
 * class produces the same markup but the injector middleware places it directly
 * before `</head>` / `</body>`, never inside sanitized content, so nothing is
 * added to the sanitizer allow-list and public content stays fully sanitized.
 *
 * Every injected tag carries a `data-pb-frontend-editor` attribute so the
 * middleware can guarantee idempotency (inject at most once).
 */
final class FrontendEditorAssets
{
    /** Marker attribute stamped on every injected tag (idempotency guard). */
    public const MARKER_ATTR = 'data-pb-frontend-editor';

    /**
     * The stylesheet `<link>` for the document head. Cache-busted per-file via
     * {@see EditorAssets} filemtime versioning.
     */
    public function headHtml(): string
    {
        $css = e(EditorAssets::url('vendor/page-builder/frontend-editor/iframe.css'));

        return '<link rel="stylesheet" '.self::MARKER_ATTR.' href="'.$css.'">';
    }

    /**
     * The boot object + iframe runtime `<script>` for just before `</body>`.
     *
     * The bundle is a self-contained Vite IIFE, so it is loaded as a CLASSIC
     * script — NOT `type="module"`, which would mis-treat the IIFE output.
     */
    public function footerHtml(string $ownerType, int|string $ownerId): string
    {
        $js = e(EditorAssets::url('vendor/page-builder/frontend-editor/iframe.js'));

        $boot = json_encode(
            [
                'ownerType' => $ownerType,
                'ownerId' => $ownerId,
                'canvasId' => FrontendEditorRenderer::CANVAS_ID,
            ],
            JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP,
        );

        return '<script '.self::MARKER_ATTR.'>window.TN_PB_FRONTEND_EDITOR = '.$boot.';</script>'
            .'<script '.self::MARKER_ATTR.' src="'.$js.'"></script>';
    }
}
