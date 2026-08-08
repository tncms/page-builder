<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Plugins\PageBuilder\Preview\DevicePresets;
use Plugins\PageBuilder\Preview\PreviewEngine;
use Plugins\PageBuilder\Preview\PreviewResourceException;
use Plugins\PageBuilder\Preview\PreviewResourceResolver;

/**
 * Serves the sandboxed, read-only runtime preview document for an iframe.
 *
 * GET (admin): renders a Design Resource (sample page / registered section /
 * registered block) at a chosen device width, using the production render
 * pipeline. The response is a self-contained HTML document with a strict CSP;
 * it contains no editor chrome, no selection, and no scripts.
 */
final class PreviewController
{
    public function __invoke(
        Request $request,
        PreviewResourceResolver $resolver,
        PreviewEngine $engine,
        DevicePresets $devices,
    ): Response {
        abort_unless(function_exists('cms_can') && cms_can('page-builder.edit'), 403);

        $source = (string) $request->query('source', 'sample');
        $reference = $request->query('ref');
        $reference = is_string($reference) ? $reference : null;

        try {
            $context = $resolver->resolve($source, $reference);
        } catch (PreviewResourceException) {
            abort(404);
        }

        $device = $devices->normalize((string) $request->query('device', DevicePresets::DEFAULT));
        $sample = $request->boolean('sample', true);
        $locale = (string) $request->query('locale', app()->getLocale());

        $context = $context
            ->withDevice($device)
            ->withSampleContent($sample)
            ->withLocale($locale);

        $result = $engine->preview($context);

        return response()
            ->view('page-builder::preview.document', [
                'kind' => $result->kind,
                'device' => $result->device,
                'width' => $result->width,
                'locale' => $result->locale,
                'head' => $result->head,
                'body' => $result->body,
            ])
            ->withHeaders([
                'Content-Type' => 'text/html; charset=UTF-8',
                'X-Content-Type-Options' => 'nosniff',
                'X-Frame-Options' => 'SAMEORIGIN',
                'Cache-Control' => 'private, max-age=0, must-revalidate',
                'Content-Security-Policy' => $this->contentSecurityPolicy(),
            ]);
    }

    /**
     * A strict CSP for the preview iframe: no scripts (inert, read-only viewer),
     * same-origin styles/fonts, images from self/https/data. Only this app may
     * embed the frame.
     */
    private function contentSecurityPolicy(): string
    {
        return implode('; ', [
            "default-src 'none'",
            "img-src 'self' https: data:",
            "style-src 'self' 'unsafe-inline'",
            "font-src 'self' https: data:",
            "frame-ancestors 'self'",
            "base-uri 'none'",
            "form-action 'none'",
        ]);
    }
}
