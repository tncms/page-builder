<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Plugins\PageBuilder\Support\FrontendEditorAssets;
use Plugins\PageBuilder\Support\FrontendEditorRenderer;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Injects the frontend-editor iframe runtime into the FINAL HTML response,
 * AFTER the theme has run `cms_html()` on the content body.
 *
 * This is the only correct seam for the editor document: `cms_html()` is applied
 * inside the theme view (last), stripping the canvas id, every `data-pb-*`
 * marker and all `<script>`/`<link>` tags, and there is no post-sanitization
 * server-side content filter to hook. So this middleware:
 *
 *   1. swaps the sanitizer-surviving {@see FrontendEditorRenderer::PLACEHOLDER}
 *      (returned through `cms.content.body`) for the TRUSTED canvas markup, and
 *   2. injects the iframe `<link>` before `</head>` and the boot object +
 *      `<script>` before `</body>`.
 *
 * It only ever acts when the editor render actually ran: that render stashes its
 * payload on the request ({@see FrontendEditorRenderer::REQUEST_KEY}), which is
 * set exclusively inside {@see FrontendEditorRenderer::active()} (pb_editor flag
 * + `page-builder.edit`). A normal public request never stashes anything, so
 * this middleware is a no-op and public output — including sanitization of
 * arbitrary user content — is completely untouched.
 */
final class InjectFrontendEditorAssets
{
    public function __construct(private readonly FrontendEditorAssets $assets) {}

    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        $response = $next($request);

        $data = $request->attributes->get(FrontendEditorRenderer::REQUEST_KEY);

        // Not an active, authorized editor render — leave the response untouched.
        if (! is_array($data) || ! isset($data['canvas'])) {
            return $response;
        }

        if (! $this->isInjectableHtml($response)) {
            return $response;
        }

        $html = $response->getContent();

        if (! is_string($html) || $html === '') {
            return $response;
        }

        // Idempotency: never inject twice.
        if (str_contains($html, FrontendEditorAssets::MARKER_ATTR)) {
            return $response;
        }

        // 1. Body: swap the plain-text placeholder for the trusted canvas markup.
        $html = str_replace(FrontendEditorRenderer::PLACEHOLDER, (string) $data['canvas'], $html);

        // 2. Head + footer: inject the runtime assets via a trusted seam.
        $html = $this->injectBefore($html, '</head>', $this->assets->headHtml());
        $html = $this->injectBefore(
            $html,
            '</body>',
            $this->assets->footerHtml((string) $data['ownerType'], $data['ownerId']),
        );

        $response->setContent($html);

        return $response;
    }

    /**
     * Only touch real, in-memory HTML responses — never JSON, redirects,
     * streamed, or binary downloads.
     */
    private function isInjectableHtml(SymfonyResponse $response): bool
    {
        if (! $response instanceof Response) {
            return false;
        }

        $contentType = (string) $response->headers->get('Content-Type', '');

        return $contentType === '' || str_contains(strtolower($contentType), 'text/html');
    }

    /**
     * Insert $insert immediately before the LAST occurrence of $anchor
     * (case-insensitive). Falls back to appending if the anchor is absent.
     */
    private function injectBefore(string $html, string $anchor, string $insert): string
    {
        $pos = strripos($html, $anchor);

        if ($pos === false) {
            return $html.$insert;
        }

        return substr($html, 0, $pos).$insert.substr($html, $pos);
    }
}
