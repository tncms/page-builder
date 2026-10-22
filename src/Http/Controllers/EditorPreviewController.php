<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Plugins\PageBuilder\DataSources\DataSourceRenderResolver;
use Plugins\PageBuilder\Support\EditableOwners;
use Plugins\PageBuilder\Support\PageBuilderManager;
use Plugins\PageBuilder\Support\RenderLocale;

/**
 * Editor visual-preview endpoint (Phase 12).
 *
 * Renders the editor's CURRENT (possibly unsaved) payload to HTML with editor
 * markers — each section/block wrapped in a `.pb-editor-render-marker` carrying
 * its id/kind/type — so the visual canvas can map DOM back to layout nodes.
 *
 * It renders the posted payload (not the stored draft) so previews reflect live
 * edits. Markers exist only here; public frontend rendering is unaffected.
 * Requires `page-builder.edit`; an unsupported owner type is a 404.
 */
final class EditorPreviewController
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless(function_exists('cms_can') && cms_can('page-builder.edit'), 403);
        abort_unless(EditableOwners::isSupported((string) $request->input('owner_type')), 404);

        /** @var PageBuilderManager $manager */
        $manager = app('page-builder');

        // The value locale must equal the editing locale (INV-PB-L10N-02), so the
        // preview resolves translatable envelopes the same way the public page will.
        // Fail-closed on a disabled locale; scoped + restored so admin locale leaks.
        $locale = RenderLocale::fromRequest($request);

        // Assign stable ids so every rendered node carries a marker, then render.
        $payload = $manager->state(
            is_array($request->input('payload')) ? $request->input('payload') : null,
        )->payload();

        // PB-XREG-2 — start from a clean diagnostics slate so only this render's
        // data-source outcomes are reported.
        if (app()->bound(DataSourceRenderResolver::class)) {
            app(DataSourceRenderResolver::class)->drainDiagnostics();
        }

        $html = RenderLocale::scope($locale, fn (): string => $manager->renderForEditor($payload));

        return response()->json([
            'success' => true,
            'data' => [
                'html' => $html,
                // Bounded data-source outcome codes for the rendered layout; never
                // provider messages, SQL, paths, or traces.
                'warnings' => $this->dataSourceWarnings(),
            ],
        ])->withHeaders([
            // Editor-only, per-user, possibly live-data HTML: never cache, never frame.
            'Cache-Control' => 'no-store, private',
            'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'none'",
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * @return list<array{code: string, component_id: ?string, field: string}>
     */
    private function dataSourceWarnings(): array
    {
        if (! app()->bound(DataSourceRenderResolver::class)) {
            return [];
        }

        return array_map(
            static fn (array $d): array => ['code' => 'data_source.'.$d['code'], 'component_id' => $d['component_id'], 'field' => $d['field']],
            app(DataSourceRenderResolver::class)->drainDiagnostics(),
        );
    }
}
