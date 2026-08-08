<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Plugins\PageBuilder\Support\PageBuilderFrontendAssets;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Injects the Page Builder component stylesheet + design-token block into the
 * document head, but ONLY when the request actually rendered Page Builder
 * content (Phase 23).
 *
 * The render pipeline sets {@see REQUEST_KEY} on the request while producing the
 * body (public {@see \Plugins\PageBuilder\Support\FrontendRenderer} success path,
 * or the editor render). A normal page with no Page Builder layout never sets
 * it, so its response is untouched and stays free of Page Builder assets. This
 * keeps the public frontend lightweight: one small stylesheet, no JavaScript.
 *
 * Because the Live Builder iframe is the same real public document, this also
 * loads the identical component CSS inside the editor — there is no separate
 * editor theme.
 */
final class InjectPageBuilderAssets
{
    /** Request attribute flag set by the renderer when PB content is emitted. */
    public const REQUEST_KEY = 'pb.frontend_assets';

    public function __construct(private readonly PageBuilderFrontendAssets $assets) {}

    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        $response = $next($request);

        if (! $request->attributes->get(self::REQUEST_KEY)) {
            return $response;
        }

        if (! $this->isInjectableHtml($response)) {
            return $response;
        }

        $html = $response->getContent();

        if (! is_string($html) || $html === '') {
            return $response;
        }

        // Idempotency.
        if (str_contains($html, PageBuilderFrontendAssets::MARKER_ATTR)) {
            return $response;
        }

        $response->setContent($this->injectBeforeHead($html, $this->assets->headHtml()));

        return $response;
    }

    private function isInjectableHtml(SymfonyResponse $response): bool
    {
        if (! $response instanceof Response) {
            return false;
        }

        $contentType = (string) $response->headers->get('Content-Type', '');

        return $contentType === '' || str_contains(strtolower($contentType), 'text/html');
    }

    /** Insert before the last </head>, or prepend as a safe fallback. */
    private function injectBeforeHead(string $html, string $insert): string
    {
        $pos = strripos($html, '</head>');

        if ($pos === false) {
            return $insert.$html;
        }

        return substr($html, 0, $pos).$insert.substr($html, $pos);
    }
}
