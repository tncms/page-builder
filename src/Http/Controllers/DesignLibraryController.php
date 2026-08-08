<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Plugins\PageBuilder\Builder\ResourcePicker\PickerMode;
use Plugins\PageBuilder\Library\DesignLibraryService;
use Plugins\PageBuilder\Library\FavoritesStoreInterface;

/**
 * Serves the Design Library admin page and its progressive-enhancement script.
 *
 * The page is server-rendered from the DesignLibraryService (repository +
 * preview-URL only). JavaScript is optional enhancement: filtering works via
 * query parameters without it.
 */
final class DesignLibraryController
{
    public function index(
        Request $request,
        DesignLibraryService $service,
        FavoritesStoreInterface $favorites,
    ): Response {
        abort_unless($this->authorized(), 403);

        $filters = [
            'kind' => $request->query('kind'),
            'category' => $request->query('category'),
            'source' => $request->query('source'),
            'vendor' => $request->query('vendor'),
            'tag' => $request->query('tag'),
            'search' => $request->query('search'),
            'favorites' => $request->boolean('favorites'),
            // Phase 26.50D — Community discovery filters (additive).
            'collection' => $request->query('collection'),
            'state' => $request->query('state'),
            'sort' => $request->query('sort'),
            'theme' => $request->query('theme'),
            'builder' => $request->query('builder'),
            'dependency' => $request->query('dependency'),
        ];

        // Phase 26.47B — Picker mode. The Builder opens the library scoped to one
        // kind to select a resource; the admin browse experience is the default and
        // renders identically when `mode` is absent.
        $mode = PickerMode::fromRequest($request->query('mode'));

        // Phase 26.50D — the Community tab drives the grid through the discovery layer
        // (collections, featured, compatibility, sort). It is never used in Picker mode
        // (the Builder selects by kind context). The default admin browse experience is
        // unchanged.
        $discoveryActive = ! $mode->isPicker()
            && ($request->query('tab') === 'community' || $this->hasDiscoveryFilter($request));

        $viewModel = $discoveryActive
            ? $service->communityViewModel($filters, $favorites->all())
            : $service->viewModel($filters, $favorites->all());
        $viewModel['tab'] = $discoveryActive ? 'community' : null;
        $viewModel['isPicker'] = $mode->isPicker();
        $viewModel['pickerKind'] = $mode->isPicker() ? $viewModel['filters']['kind'] : null;

        $viewModel['scriptUrl'] = $this->scriptUrl();
        $viewModel['target'] = $this->importTarget($request);
        $viewModel['csrfToken'] = csrf_token();
        $viewModel['importPlanUrl'] = $this->routeOrPath('page-builder.library.import.plan', 'page-builder/library/import/plan');
        $viewModel['importUrl'] = $this->routeOrPath('page-builder.library.import', 'page-builder/library/import');
        // Phase 26.47 — Design Package export/import (portable .tncms-design files).
        $viewModel['packageScriptUrl'] = $this->routeOrPath('page-builder.library.package.script', 'page-builder/library/package-import.js');
        $viewModel['packageExportUrl'] = $this->routeOrPath('page-builder.library.export', 'page-builder/library/export');
        $viewModel['packageValidateUrl'] = $this->routeOrPath('page-builder.library.package.validate', 'page-builder/library/package/validate');
        $viewModel['packageImportUrl'] = $this->routeOrPath('page-builder.library.package.import', 'page-builder/library/package/import');
        // Phase 26.47D — Page Template destination workflow ("Use This Template").
        $viewModel['templateScriptUrl'] = $this->routeOrPath('page-builder.library.template.script', 'page-builder/library/template.js');
        $viewModel['templateDestinationsUrl'] = $this->routeOrPath('page-builder.library.template.destinations', 'page-builder/library/template/destinations');
        $viewModel['templateCreateUrl'] = $this->routeOrPath('page-builder.library.template.create-apply', 'page-builder/library/template/create-apply');
        $viewModel['templateApplyExistingUrl'] = $this->routeOrPath('page-builder.library.template.apply-existing', 'page-builder/library/template/apply-existing');

        return response()->view('page-builder::design-library.index', $viewModel);
    }

    /**
     * Serves the Design Library ES module (no build step; the source file IS the
     * served asset).
     */
    public function script(): Response
    {
        $path = dirname(__DIR__, 3).'/resources/js/design-library/design-library.js';
        $js = is_file($path) ? (string) file_get_contents($path) : '';

        return response($js, 200, [
            'Content-Type' => 'text/javascript; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    /**
     * Whether the request carries any Community discovery filter (collection / curated
     * state / compatibility). Presence of one switches the grid to the discovery layer.
     */
    private function hasDiscoveryFilter(Request $request): bool
    {
        foreach (['collection', 'state', 'theme', 'builder', 'dependency'] as $key) {
            $value = $request->query($key);
            if (is_string($value) && trim($value) !== '') {
                return true;
            }
        }

        return false;
    }

    private function authorized(): bool
    {
        if (! function_exists('cms_can')) {
            return false;
        }

        return cms_can('page-builder.view') || cms_can('page-builder.edit');
    }

    private function scriptUrl(): string
    {
        return $this->routeOrPath('page-builder.library.script', 'page-builder/library/design-library.js');
    }

    private function routeOrPath(string $name, string $path): string
    {
        try {
            return route($name);
        } catch (\Throwable) {
            return url($path);
        }
    }

    /**
     * Optional import target (the document being edited). Present only when a valid
     * supported owner is supplied — otherwise page templates cannot be imported.
     *
     * @return array<string, mixed>|null
     */
    private function importTarget(Request $request): ?array
    {
        $ownerType = $request->query('ownerType');
        $ownerId = $request->query('ownerId');

        if (! is_string($ownerType) || $ownerType === '' || ($ownerId === null || $ownerId === '')) {
            return null;
        }

        if (! \Plugins\PageBuilder\Support\EditableOwners::isSupported($ownerType)) {
            return null;
        }
        if (\Plugins\PageBuilder\Support\EditableOwners::resolve($ownerType, $ownerId) === null) {
            return null;
        }

        return [
            'ownerType' => $ownerType,
            'ownerId' => $ownerId,
            'label' => \Plugins\PageBuilder\Support\EditableOwners::label($ownerType),
        ];
    }
}
