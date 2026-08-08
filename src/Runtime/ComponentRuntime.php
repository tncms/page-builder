<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Runtime;

use Illuminate\Support\Facades\View;
use Plugins\PageBuilder\Contracts\ComponentInstance;
use Plugins\PageBuilder\Support\ColumnWidth;
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

    public function __construct(private readonly RuntimeHookManager $hooks) {}

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

        // Phase 19 — apply box-model spacing (data.spacing) as an inline style on
        // the component root. Purely additive: components without spacing data are
        // unchanged. Runs in both public and editor mode, before editor wrapping,
        // so the spacing lands on the real element (not the marker div).
        if ($html !== '') {
            $html = SpacingStyle::apply($html, $instance->data());
        }

        if ($this->editor && $html !== '') {
            $html = $this->wrapForEditor($instance, $html);
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
            $style = $basis === null
                // Auto: flex freely to fill the remaining row space.
                ? 'flex:1 1 0;min-width:0;box-sizing:border-box;'
                : 'flex:0 0 '.$basis.';max-width:'.$basis.';min-width:0;box-sizing:border-box;--pb-column-basis:'.$basis.';';

            return [' pb-editor-render-marker--column', ' style="'.$style.'"'];
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

            return View::make($view, $data)->render();
        }

        if ($view === '') {
            return $this->missingBlockNotice($instance->type());
        }

        $data = $instance->renderData();
        $data['pbEditor'] = $this->editor;

        return View::make($view, $data)->render();
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
