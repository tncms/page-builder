<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Support;

use Illuminate\Http\Request;
use Plugins\PageBuilder\Editor\Translation\EffectiveEditorOwnerResolver;
use Plugins\PageBuilder\Http\Middleware\InjectPageBuilderAssets;
use Plugins\PageBuilder\Support\DynamicStyle\DynamicStyleRegistry;
use TheNguyen\CMS\Models\Content;

/**
 * Renders a page/post owner inside the FRONTEND EDITOR iframe (Phase 16).
 *
 * This is the editor-only counterpart to {@see FrontendRenderer}. Where the
 * public renderer only ever shows `published_payload` with no markers, this one
 * shows the DRAFT payload (falling back to published, then the classic body)
 * WITH editor markers, wrapped in the `#pb-frontend-canvas` region the iframe
 * runtime scans, and injects the small iframe editor runtime + a tiny boot
 * object.
 *
 * Safety is enforced by {@see active()}: it returns true only when the request
 * carries the `pb_editor` flag AND the authenticated user holds
 * `page-builder.edit`. A normal public visitor (no session / no permission)
 * never trips it, so draft payloads and editor assets stay private and the
 * public page ({@see FrontendRenderer}) is completely untouched.
 */
final class FrontendEditorRenderer
{
    public const CANVAS_ID = 'pb-frontend-canvas';

    /**
     * Plain-text sentinel returned through the (sanitized) `cms.content.body`
     * filter in editor mode. It carries no HTML tags or attributes, so it
     * survives `cms_html()` untouched (verified) where the real canvas markup —
     * `id`, `data-pb-*` markers, `<script>`/`<link>` — would all be stripped.
     * {@see \Plugins\PageBuilder\Http\Middleware\InjectFrontendEditorAssets}
     * swaps it for the TRUSTED, unsanitized canvas after the theme has rendered.
     */
    public const PLACEHOLDER = 'PBFRONTENDEDITORCANVASb7e1c9PLACEHOLDER';

    /**
     * Request-attribute key under which the editor render stashes the trusted
     * canvas HTML + owner identity for the injector middleware to consume.
     */
    public const REQUEST_KEY = 'pb.frontend_editor';

    public function __construct(
        private readonly LayoutStore $layouts,
        private readonly PageBuilderManager $manager,
        private readonly DynamicStyleRegistry $dynamicStyles,
        private readonly EffectiveEditorOwnerResolver $owners,
    ) {}

    /**
     * Whether the current request should render this owner in editor mode.
     */
    public function active(Content $content, ?Request $request = null): bool
    {
        if (! EditableOwners::isSupported((string) $content->type)) {
            return false;
        }

        $request ??= request();

        if (! $request instanceof Request || ! $request->boolean('pb_editor')) {
            return false;
        }

        return function_exists('cms_can') && cms_can('page-builder.edit');
    }

    /**
     * Editor-mode replacement for the sanitized `cms.content.body`.
     *
     * Instead of returning the canvas markup (which `cms_html()` would gut —
     * stripping the canvas id and every `data-pb-component-id` marker), this
     * stashes the TRUSTED canvas HTML + owner identity on the request for the
     * injector middleware and returns a plain-text {@see PLACEHOLDER} that
     * survives sanitization. Only ever reached through {@see active()}.
     */
    public function registerForRequest(Content $content, string $classicBody, ?Request $request = null): string
    {
        $request ??= request();

        // Fresh runtime-style set for this editor render, so the injected head
        // rules reflect only the current canvas (mirrors the public renderer).
        $this->dynamicStyles->reset();

        if ($request instanceof Request) {
            $request->attributes->set(self::REQUEST_KEY, [
                'canvas' => $this->canvasHtml($content, $classicBody),
                'ownerType' => (string) $content->type,
                'ownerId' => $content->getKey(),
            ]);
            // The editor iframe is the real public document, so it also needs the
            // component stylesheet + token block in <head>.
            $request->attributes->set(InjectPageBuilderAssets::REQUEST_KEY, true);
        }

        return self::PLACEHOLDER;
    }

    /**
     * The trusted editor canvas: the marker-annotated Page Builder HTML wrapped
     * in the `#pb-frontend-canvas` region the iframe runtime scans. Contains NO
     * executable assets — those are injected separately by the middleware via a
     * trusted document seam, never through sanitized content.
     */
    public function canvasHtml(Content $content, string $classicBody): string
    {
        $ownerType = (string) $content->type;
        $ownerId = $content->getKey();

        return $this->wrapCanvas($this->renderInner($ownerType, $ownerId, $classicBody));
    }

    /**
     * Inner canvas HTML: draft first, then published, both rendered WITH editor
     * markers; if the owner has no builder content, the classic body is shown so
     * the editor still has visual context (the shell replaces it on first edit).
     * Any failure degrades to the classic body — the editor can never 500 a page.
     */
    private function renderInner(string $ownerType, int|string $ownerId, string $classicBody): string
    {
        // LT-M1B Increment 2 — preview resolves the SAME effective owner as the
        // editor. The editor's iframe URL carries `?locale=`, so a VI preview shows
        // the VI draft (content_translation owner), never the source layout. Read-
        // only + fail-soft: a missing/invalid locale falls back to the canonical
        // owner and this NEVER provisions or writes.
        [$ownerType, $ownerId] = $this->owners->resolveForReadOrCanonical(
            $ownerType,
            $ownerId,
            $this->requestLocale(),
        );

        $layout = $this->layouts->find($ownerType, $ownerId);

        if ($layout !== null && $layout->isPageBuilderMode()) {
            $payload = $layout->draft_payload ?? $layout->published_payload;

            if (is_array($payload)) {
                try {
                    // Assign stable ids first (via the state engine) so every
                    // rendered node carries a marker the iframe runtime can map,
                    // then render in editor mode. Mirrors EditorPreviewController.
                    // Scope the render to the SAME locale the owner was resolved
                    // under (INV-PB-L10N-02), fail-soft + restored, so translatable
                    // values match the effective owner's locale, not the ambient one.
                    $withIds = $this->manager->state($payload)->payload();
                    $html = RenderLocale::scope(
                        RenderLocale::enabled($this->requestLocale()),
                        fn (): string => $this->manager->renderForEditor($withIds),
                    );

                    if (trim($html) !== '') {
                        return $html;
                    }
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }

        return $classicBody;
    }

    private function wrapCanvas(string $inner): string
    {
        return '<div id="'.self::CANVAS_ID.'" data-pb-frontend-canvas>'.$inner.'</div>';
    }

    /**
     * The editor locale carried on the iframe request (`?locale=`), or null for the
     * source locale. Editor context only — the effective owner is re-resolved from
     * it server-side. This path is already gated by {@see active()} (pb_editor flag
     * + `page-builder.edit`), so the parameter is trusted-authenticated input.
     */
    private function requestLocale(): ?string
    {
        $locale = request()->input('locale');

        if (! is_string($locale)) {
            return null;
        }

        $locale = trim($locale);

        return $locale === '' ? null : $locale;
    }
}
