<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Runtime;

use Illuminate\Support\Facades\View;
use Plugins\PageBuilder\Contracts\ComponentInstance;
use Plugins\PageBuilder\DataSources\DataSourceFields;
use Plugins\PageBuilder\DataSources\DataSourceRenderResolver;
use Plugins\PageBuilder\Support\ColumnWidth;
use Plugins\PageBuilder\Support\ColumnWidthRegistry;
use Plugins\PageBuilder\Support\ResponsiveSpacingStyle;
use Plugins\PageBuilder\Support\SpacingStyle;

/**
 * Executes the component lifecycle and renders instances to HTML (Phase 7).
 *
 * For each instance it: boots it once (dispatching beforeResolve/afterResolve
 * hooks around `resolve()`), skips it when not visible, then renders it
 * (dispatching beforeRender/afterRender hooks). Sections compose their child
 * blocks into `$children`; a block with no resolvable view emits nothing (an
 * authoring comment in debug) — reproducing the pre-runtime renderer's output
 * byte-for-byte for the built-in library.
 */
class ComponentRuntime
{
    /**
     * Editor preview mode (Phase 12). When on, each rendered component is wrapped
     * in a marker element carrying its id/kind/type so the visual canvas can map
     * DOM back to layout nodes. OFF by default, so public rendering is untouched.
     */
    private bool $editor = false;

    /**
     * Heading-context: whether the HOST document already renders a primary title
     * heading (the theme's page `<h1>`) above this layout — the generic fact the
     * core page-title visibility contract exposes (PB-FREE-LIBRARY-DESIGN-1-E-H1).
     * When true the builder's primary hero heading renders at the next semantic
     * level (`<h2>`) so the document keeps exactly one `<h1>`; when false the hero
     * owns the single `<h1>`. It is a transient render fact — NEVER persisted into
     * the payload — and defaults to false (the pre-contract behaviour, and the
     * documented default for catalog previews not attached to a page).
     */
    private bool $externalHeadingPresent = false;

    public function __construct(
        private readonly RuntimeHookManager $hooks,
        private readonly ResponsiveSpacingStyle $responsiveSpacing,
    ) {}

    public function hooks(): RuntimeHookManager
    {
        return $this->hooks;
    }

    public function setEditorMode(bool $editor): void
    {
        $this->editor = $editor;
    }

    public function editorMode(): bool
    {
        return $this->editor;
    }

    public function setExternalHeadingPresent(bool $present): void
    {
        $this->externalHeadingPresent = $present;
    }

    public function externalHeadingPresent(): bool
    {
        return $this->externalHeadingPresent;
    }

    /**
     * Run the resolve lifecycle for an instance (idempotent), firing the
     * beforeResolve/afterResolve hooks around it.
     */
    public function boot(ComponentInstance $instance): void
    {
        if ($instance->isResolved()) {
            return;
        }

        $this->hooks->dispatch('beforeResolve', $instance);
        $instance->resolve();
        $this->hooks->dispatch('afterResolve', $instance);
    }

    /**
     * Boot + render an instance to HTML (empty string when not visible).
     */
    public function render(ComponentInstance $instance): string
    {
        $this->boot($instance);

        if (! $instance->isVisible()) {
            return '';
        }

        $this->hooks->dispatch('beforeRender', $instance);
        $instance->beforeRender();

        $html = $this->toHtml($instance);

        $instance->afterRender();
        $this->hooks->dispatch('afterRender', $instance);

        // Phase 19 / PB-PROD-1 — apply box-model spacing (data.spacing) to the
        // component root. Purely additive: components without spacing data are
        // unchanged. Runs in both public and editor mode, before editor wrapping,
        // so the spacing lands on the real element (not the marker div).
        if ($html !== '') {
            $html = $this->applySpacing($html, $instance->data());
        }

        if ($this->editor && $html !== '') {
            $html = $this->wrapForEditor($instance, $html);
        }

        return $html;
    }

    /**
     * Apply box-model spacing to a component root (PB-PROD-1).
     *
     * The desktop/base value is injected inline (byte-identical to Phase 19 for a
     * non-responsive flat box; the desktop entry for a responsive envelope) so the
     * sanitiser-free editor/preview shows it. When the spacing is responsive, the
     * per-breakpoint rules are registered through the runtime style pipeline and
     * their sanitiser-proof `.pb-ds-*` classes are added to the root, so the
     * anonymous frontend renders desktop/tablet/mobile faithfully.
     *
     * @param  array<string, mixed>  $data
     */
    private function applySpacing(string $html, array $data): string
    {
        $spacing = $data['spacing'] ?? null;

        $base = $this->responsiveSpacing->baseSpacing($spacing);
        $html = SpacingStyle::inject($html, SpacingStyle::css($base));

        $classes = $this->responsiveSpacing->register($spacing);
        if ($classes !== []) {
            $html = ResponsiveSpacingStyle::applyClasses($html, $classes);
        }

        return $html;
    }

    /**
     * Wrap rendered HTML in an editor marker so the visual canvas can map a
     * clicked/hovered DOM element back to its layout node (Phase 12). Only nodes
     * with a stable id are wrapped; this runs only in editor preview mode.
     */
    private function wrapForEditor(ComponentInstance $instance, string $html): string
    {
        $id = $instance->id();
        if ($id === null || $id === '') {
            return $html;
        }

        [$extraClass, $style] = $this->markerLayout($instance);

        return '<div class="pb-editor-render-marker'.$extraClass.'"'
            .' data-pb-component-id="'.e($id).'"'
            .' data-pb-component-kind="'.e($instance->kind()).'"'
            .' data-pb-component-type="'.e($instance->type()).'"'
            .$style
            .'>'
            .$html
            .'</div>';
    }

    /**
     * Layout class + inline style for a composition node's editor marker
     * (Phase 25.3.1; hardened 25.3.2 §3/§4).
     *
     * In editor mode the marker div — not the `.pb-container` / `.pb-column`
     * inside it — is the real flex participant, so the marker must own the layout
     * geometry or the composition collapses:
     *   - a CONTAINER marker must be a full-width block, or the row shrink-wraps;
     *   - a COLUMN marker must carry the flex-basis (with `box-sizing:border-box`
     *     so the column's gutter padding stays inside the basis and never pushes
     *     the sum over 100%).
     * A type-specific class is added too so a global marker rule can never
     * silently override the inline geometry. Non-composition markers get nothing.
     *
     * @return array{0: string, 1: string}  [extra class, ' style="…"' or '']
     */
    private function markerLayout(ComponentInstance $instance): array
    {
        $type = $instance->type();

        if ($type === 'container') {
            return [
                ' pb-editor-render-marker--container',
                ' style="display:block;width:100%;max-width:100%;min-width:0;box-sizing:border-box;"',
            ];
        }

        if ($type === 'column') {
            $basis = ColumnWidth::basis($instance->data()['width'] ?? null);

            if ($basis === null) {
                // Auto: flex freely to fill the remaining row space (class-owned).
                return [' pb-editor-render-marker--column pb-editor-render-marker--column-auto', ''];
            }

            // Geometry authority (PB-FREE-LIBRARY-DESIGN-1-C): the marker carries the
            // basis through the SAME sanitizer-proof `.pb-cw-*` class the public
            // column uses, so one CSS authority sizes it on every surface and the
            // certified tablet stacking media query wins here too — no inline final
            // flex/max-width that a media query cannot override. `.pb-cw-*` only
            // emits for a safe percentage basis, so unsafe widths carry no geometry.
            $cw = app(ColumnWidthRegistry::class)->classFor($basis);
            $extra = ' pb-editor-render-marker--column';
            if ($cw !== null) {
                $extra .= ' '.$cw;
            }

            return [$extra, ''];
        }

        return ['', ''];
    }

    private function toHtml(ComponentInstance $instance): string
    {
        $view = $instance->renderView();

        if ($instance->kind() === 'section') {
            $children = '';
            foreach ($instance->children() as $child) {
                $children .= $this->render($child);
            }

            $data = $instance->renderData();
            $data['children'] = $children;
            // Phase 23: lets views render editor-only placeholders (drop zones,
            // "Choose an image", …). Never true on the public frontend.
            $data['pbEditor'] = $this->editor;
            // Heading-context (PB-FREE-LIBRARY-DESIGN-1-E-H1): whether an external
            // document title <h1> is already present, so a section owning the
            // primary heading (hero) can pick the correct semantic rank. Transient
            // render fact — not part of the stored payload.
            $data['pbDocumentTitleRendered'] = $this->externalHeadingPresent;
            $data = $this->withDataSources($instance, $data);

            return View::make($view, $data)->render();
        }

        if ($view === '') {
            return $this->missingBlockNotice($instance->type());
        }

        $data = $instance->renderData();
        $data['pbEditor'] = $this->editor;
        $data = $this->withDataSources($instance, $data);

        return View::make($view, $data)->render();
    }

    /**
     * PB-XREG-2 — add `$dataSources` (plain, freshly fetched, never persisted)
     * for a component whose definition declares data-source fields. Components
     * without such fields receive exactly the view data they always did.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withDataSources(ComponentInstance $instance, array $data): array
    {
        // Resolved per render (not injected) so the current request's registry
        // state is always the authority, even in long-lived workers.
        if (DataSourceFields::in($instance->definition()) === [] || ! app()->bound(DataSourceRenderResolver::class)) {
            return $data;
        }

        $resolved = app(DataSourceRenderResolver::class)->resolve($instance, $this->editor);

        if ($resolved !== null) {
            $data['dataSources'] = $resolved;
        }

        return $data;
    }

    /**
     * Authoring aid: a comment for an unrenderable block, only in debug.
     */
    private function missingBlockNotice(string $type): string
    {
        if (! (bool) config('app.debug')) {
            return '';
        }

        return '<!-- page-builder: no view for block ['.e($type).'] -->';
    }
}
