<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Plugins\PageBuilder\Support\EditableOwners;
use Plugins\PageBuilder\Support\PageBuilderManager;
use Plugins\PageBuilder\Support\RenderLocale;

/**
 * Editor component-preview endpoint (Phase 21 — Live Component Runtime).
 *
 * Renders ONE component (by id) from the editor's CURRENT (possibly unsaved)
 * payload to editor-marked HTML, so the frontend Live Builder can reconcile a
 * single component through the real PHP/Blade runtime — replacing only that
 * component's marker in the iframe instead of swapping the whole canvas.
 *
 * Like {@see EditorPreviewController}, it renders the posted payload (never the
 * stored draft), assigns stable ids first (BuilderState), and saves nothing.
 * Markers exist only here; public frontend rendering is unaffected. Requires
 * `page-builder.edit`; an unsupported owner type — or a missing owner / unknown
 * component id — is a 404. The HTML it returns is produced entirely by the
 * trusted Blade renderer and carries no editor/shell bundle assets.
 */
final class ComponentPreviewController
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless(function_exists('cms_can') && cms_can('page-builder.edit'), 403);

        $ownerType = (string) $request->input('owner_type');
        abort_unless(EditableOwners::isSupported($ownerType), 404);

        // When an owner id is supplied, it must resolve to a real record. (The
        // preview is stateless, but this keeps the endpoint owner-scoped.)
        $ownerId = $request->input('owner_id');
        if ($ownerId !== null && $ownerId !== '') {
            abort_if(EditableOwners::resolve($ownerType, $ownerId) === null, 404);
        }

        $componentId = (string) $request->input('component_id');
        abort_if(trim($componentId) === '', 404);

        /** @var PageBuilderManager $manager */
        $manager = app('page-builder');

        // Reconcile renders one component through the real Blade runtime — it must
        // resolve translatable values under the EDITING locale (INV-PB-L10N-02), not
        // the ambient admin locale, or a component's fields render in mixed locales.
        // Fail-closed on a disabled locale; scoped + restored around the render only.
        $locale = RenderLocale::fromRequest($request);

        // Assign stable ids so posted-but-unsaved nodes carry a marker id, then
        // render only the requested component (with children if it has any).
        $payload = $manager->state(
            is_array($request->input('payload')) ? $request->input('payload') : null,
        )->payload();

        $html = RenderLocale::scope($locale, fn (): ?string => $manager->renderComponentForEditor($payload, $componentId));

        // Unknown / missing component: a safe 404 the client falls back from.
        abort_if($html === null, 404);

        return response()->json([
            'success' => true,
            'data' => [
                'component_id' => $componentId,
                'html' => $html,
                'kind' => $this->kindOf($payload, $componentId),
                'type' => $this->typeOf($payload, $componentId),
                'warnings' => [],
            ],
        ]);
    }

    /**
     * The located component's kind ('section'|'block'), read from the normalized
     * payload — best-effort metadata for the client (never throws).
     *
     * @param  array<string, mixed>  $payload
     */
    private function kindOf(array $payload, string $componentId): string
    {
        return $this->locate($payload, $componentId)['kind'] ?? '';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function typeOf(array $payload, string $componentId): string
    {
        return $this->locate($payload, $componentId)['type'] ?? '';
    }

    /**
     * Depth-first search for a node's { kind, type } in a normalized payload.
     *
     * @param  array<string, mixed>  $payload
     * @return array{kind?: string, type?: string}
     */
    private function locate(array $payload, string $componentId): array
    {
        foreach ($payload['sections'] ?? [] as $section) {
            if (! is_array($section)) {
                continue;
            }
            if (($section['id'] ?? null) === $componentId) {
                return ['kind' => 'section', 'type' => (string) ($section['type'] ?? '')];
            }
            $hit = $this->locateBlock($section['blocks'] ?? [], $componentId);
            if ($hit !== []) {
                return $hit;
            }
        }

        return [];
    }

    /**
     * @param  mixed  $blocks
     * @return array{kind?: string, type?: string}
     */
    private function locateBlock(mixed $blocks, string $componentId): array
    {
        if (! is_array($blocks)) {
            return [];
        }

        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }
            if (($block['id'] ?? null) === $componentId) {
                return ['kind' => 'block', 'type' => (string) ($block['type'] ?? '')];
            }
            $nested = $this->locateBlock($block['data']['blocks'] ?? ($block['blocks'] ?? []), $componentId);
            if ($nested !== []) {
                return $nested;
            }
        }

        return [];
    }
}
