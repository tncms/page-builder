<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Http\Controllers;

use Illuminate\Http\Request;
use Plugins\PageBuilder\Editor\Translation\EditorOwnerResolutionException;
use Plugins\PageBuilder\Editor\Translation\EffectiveEditorOwnerResolver;
use Plugins\PageBuilder\Support\EditableOwners;
use Plugins\PageBuilder\Support\FrontendEditorRenderer;
use Symfony\Component\HttpFoundation\Response;
use TheNguyen\CMS\Http\Controllers\FrontendController;

/**
 * Serves the PRIVATE, authenticated editor-document for the frontend editor iframe
 * (PB-DRAFT-PREVIEW-R1).
 *
 * The frontend editor canvas is an iframe. Its document used to be the owner's REAL
 * public page ({@see \Plugins\PageBuilder\Support\EditorUrls::publicUrl()} →
 * `content_url()`), so an UNPUBLISHED page/post returned Core's public 404 and the
 * Page Builder bridge never initialised. This route replaces that public dependency
 * with a Page Builder-owned, identity-based, private document that:
 *
 *   1. requires the real admin auth context + `page-builder.edit` (auth precedes
 *      any draft/locale/version disclosure — INV-DRAFT-PREVIEW-02/03/04);
 *   2. re-resolves + validates the editing locale through the ONE resolver, failing
 *      closed on an invalid/disabled locale (INV-DRAFT-PREVIEW-09);
 *   3. renders the persisted DRAFT through Core's status-gate-free
 *      {@see FrontendController::renderPreviewable()} seam — the SAME theme/render
 *      pipeline as the published page — with the editor canvas + bridge injected by
 *      the existing `cms.content.body` filter + InjectFrontendEditorAssets middleware
 *      (INV-DRAFT-PREVIEW-05);
 *   4. is stamped non-cacheable + noindex by the Core PreviewManager
 *      (INV-DRAFT-PREVIEW-07).
 *
 * It NEVER makes draft content public: the ordinary public URL keeps Core's 404 for
 * unpublished owners (INV-DRAFT-PREVIEW-01/06), and opening this document performs no
 * publish or write (INV-DRAFT-PREVIEW-10). Loaded in the `web` group; auth is enforced
 * here (mirrors the sibling Runtime PreviewController), not by making the draft public.
 */
final class EditorDocumentController
{
    public function __invoke(
        Request $request,
        string $ownerType,
        string $ownerId,
        EffectiveEditorOwnerResolver $owners,
        FrontendController $frontend,
    ): Response {
        // 1. Authorisation precedes any disclosure (INV-02/03/04).
        abort_unless(function_exists('cms_can') && cms_can('page-builder.edit'), 403);

        // 2. Fail closed on an unsupported owner type before touching the database.
        abort_unless(EditableOwners::isSupported($ownerType), 404);

        $owner = EditableOwners::resolve($ownerType, $ownerId);
        abort_if($owner === null, 404);

        // 3. Editing-locale context (editor param only). The server re-resolves the
        //    effective (locale-specific) layout owner through the ONE resolver and
        //    fails closed on an invalid / disabled / out-of-group locale — the editor
        //    document never silently renders the source layout for a bad context.
        $requestedLocale = $this->localeFromRequest($request);

        try {
            $owners->resolve($ownerType, $owner->getKey(), $requestedLocale);
        } catch (EditorOwnerResolutionException $e) {
            abort($e->statusCode, $e->getMessage());
        }

        // 4. Declare editor intent for THIS render without the public `?pb_editor=1`
        //    flag, so the shared cms.content.body branch renders the DRAFT with editor
        //    markers and the injector middleware boots the bridge. The renderer reads
        //    `?locale=` off this request to resolve the effective owner's draft.
        $request->attributes->set(FrontendEditorRenderer::EDITOR_DOCUMENT_ATTR, true);

        // 5. Render the draft through Core's status-gate-free preview seam — the exact
        //    theme + render pipeline the published page uses (no logic duplicated).
        $response = $frontend->renderPreviewable($owner, $requestedLocale);

        // 6. Private, non-cacheable, non-indexable response (INV-07), through the Core
        //    PreviewManager so preview header policy stays owned by Core.
        return $this->applyPrivateHeaders($response);
    }

    /**
     * The requested editing locale (context only) from the query string, or null for
     * the source locale. A blank value is treated as absent.
     */
    private function localeFromRequest(Request $request): ?string
    {
        $locale = $request->query('locale');

        if (! is_string($locale)) {
            return null;
        }

        $locale = trim($locale);

        return $locale === '' ? null : $locale;
    }

    /**
     * Stamp the response private + noindex + no-store. Delegates to the Core
     * PreviewManager when available so header policy stays Core-owned; degrades to a
     * local, equivalent stamp when the preview binding is absent (isolated tests).
     */
    private function applyPrivateHeaders(Response $response): Response
    {
        if (app()->bound('cms.preview')) {
            return app('cms.preview')->applyPreviewHeaders($response);
        }

        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }
}
