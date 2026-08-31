<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Support;

use Illuminate\Http\Request;
use Plugins\PageBuilder\Editor\Translation\EffectiveEditorOwnerResolver;
use Plugins\PageBuilder\Http\Middleware\InjectPageBuilderAssets;
use Plugins\PageBuilder\Support\DynamicStyle\DynamicStyleRegistry;
use TheNguyen\CMS\Localization\Contracts\PublicLocaleContextContract;
use Throwable;

/**
 * Public frontend render switch (Phase 14.3).
 *
 * Decides whether a content owner (page/post) renders its Page Builder published
 * layout or its classic body. Registered against the core `cms.content.body`
 * filter from the plugin service provider — no core code is modified.
 *
 * Rules:
 *   - Only supported owners (page/post) are considered.
 *   - Only owners in builder mode WITH a published payload render the builder.
 *   - Only `published_payload` is ever used — never `draft_payload`.
 *   - Rendering goes through the existing {@see LayoutRenderer::render()} (public
 *     mode: no editor markers, no editor assets).
 *   - Any failure or empty output falls back to the classic body, so a malformed
 *     payload can never 500 a public page.
 */
final class FrontendRenderer
{
    public function __construct(
        private readonly LayoutStore $layouts,
        private readonly LayoutRenderer $renderer,
        private readonly ColumnWidthRegistry $columnWidths,
        private readonly DynamicStyleRegistry $dynamicStyles,
        private readonly EffectiveEditorOwnerResolver $owners,
    ) {}

    public function bodyFor(string $ownerType, int|string $ownerId, string $classicBody, bool $externalTitleRendered = false): string
    {
        if (! EditableOwners::isSupported($ownerType)) {
            return $classicBody;
        }

        // LT-M1B Increment 2 — locale-aware public render. Resolve the effective
        // layout owner for the current request locale through the SAME resolver the
        // editor uses: the default/source locale (and a dormant translation feature)
        // resolves to the canonical owner (legacy behaviour), a non-default locale to
        // the (content_translation, id) owner. This is read-only and fail-soft — it
        // never clones, writes, or throws (a bad locale falls back to canonical).
        [$ownerType, $ownerId] = $this->owners->resolveForReadOrCanonical(
            $ownerType,
            $ownerId,
            $this->currentLocale(),
        );

        $layout = $this->layouts->find($ownerType, $ownerId);

        // Fallback policy (LT-M1B Inc2, §8): classic owners, draft-only owners, and
        // owners never published keep the classic body — for a non-default locale
        // whose translated layout is not yet published this yields the translated
        // classic content. We NEVER create or write a target layout during render.
        // hasPublished() is null-safe; draft_payload is ignored.
        if ($layout === null || ! $layout->isPageBuilderMode() || ! $layout->hasPublished()) {
            return $classicBody;
        }

        // Start each public render with a clean runtime-style set so the injected
        // `.pb-cw-*` / `.pb-ds-*` head rules reflect only this page's content.
        $this->columnWidths->reset();
        $this->dynamicStyles->reset();

        // Heading-context (PB-FREE-LIBRARY-DESIGN-1-E-H1): tell the runtime whether
        // the host theme already renders the page title <h1> above this layout, so
        // the builder's primary hero heading takes the correct semantic rank. Reset
        // afterwards so this transient fact never leaks into another render, and the
        // stored payload is never touched.
        $runtime = $this->renderer->runtime();
        $runtime->setExternalHeadingPresent($externalTitleRendered);

        try {
            $html = $this->renderer->render($layout->published_payload);
        } catch (Throwable $e) {
            report($e);

            return $classicBody;
        } finally {
            $runtime->setExternalHeadingPresent(false);
        }

        if (trim($html) === '') {
            return $classicBody;
        }

        // Signal the middleware to load the component stylesheet + token block
        // into <head> — only when Page Builder content was actually rendered, so
        // classic pages stay free of these assets.
        $request = request();
        if ($request instanceof Request) {
            $request->attributes->set(InjectPageBuilderAssets::REQUEST_KEY, true);
        }

        return $html;
    }

    /**
     * The current public-content locale from Core's read-only locale context, or
     * null when Core localization is unavailable (isolated plugin contexts) — in
     * which case the render stays on the canonical owner. Never throws.
     */
    private function currentLocale(): ?string
    {
        if (! app()->bound(PublicLocaleContextContract::class)) {
            return null;
        }

        try {
            return app(PublicLocaleContextContract::class)->current();
        } catch (Throwable) {
            return null;
        }
    }
}
