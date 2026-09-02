<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Plugins\PageBuilder\Library\ResourceCatalogService;
use Plugins\PageBuilder\Support\CatalogCategoryLabels;
use Plugins\PageBuilder\Support\CatalogLocale;

/**
 * Read-only editor endpoint that serves the unified, metadata-first Resource Browser
 * catalog (PB-TEMPLATE-REGISTRY-2-B).
 *
 * Same authorization as every other editor endpoint (`page-builder.edit`), resolved
 * before any resource metadata is disclosed. Labels resolve for the Admin UI locale.
 * It never returns a resource payload — the browser list is metadata only.
 */
final class ResourceCatalogController
{
    public function __construct(
        private readonly ResourceCatalogService $catalog,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        abort_unless(function_exists('cms_can') && cms_can('page-builder.edit'), 403);

        $locale = CatalogLocale::admin();

        return response()->json([
            'success' => true,
            'data' => [
                ...$this->catalog->catalog($locale, CatalogLocale::fallback()),
                // Server-resolved browser chrome strings (the Vue editor has no client
                // i18n; Core/plugin JSON translations own localization). Resolved at the
                // Admin UI locale so the browser interface matches its catalog labels.
                'strings' => $this->strings($locale),
                // Admin-UI-locale labels for the invariant category keys so the category
                // filter never shows a raw slug (PB-FREE-LIBRARY-DESIGN-1-F).
                'categoryLabels' => CatalogCategoryLabels::map($locale, CatalogLocale::fallback()),
            ],
        ]);
    }

    /**
     * The browser interface strings, resolved for the Admin UI locale via the plugin's
     * JSON translations. Keyed by stable slugs so the Vue layer never depends on a
     * translation key. Availability reasons are allowlisted here — never raw exceptions.
     *
     * @return array<string, string>
     */
    private function strings(?string $locale): array
    {
        $t = static fn (string $key): string => (string) __($key, [], $locale);

        return [
            'title' => $t('Resource Browser'),
            'patterns' => $t('Patterns'),
            'templates' => $t('Templates'),
            'blocks' => $t('Blocks'),
            'search' => $t('Search resources'),
            'source' => $t('Source'),
            'category' => $t('Category'),
            'availability' => $t('Availability'),
            'all' => $t('All'),
            'available' => $t('Available'),
            'unavailable' => $t('Unavailable'),
            'sourceBuiltin' => $t('Built-in'),
            'sourceTheme' => $t('Theme'),
            'clearFilters' => $t('Clear filters'),
            'results' => $t('results'),
            'noResults' => $t('No matching resources'),
            'loading' => $t('Loading resources…'),
            'empty' => $t('No resources yet'),
            'error' => $t('Could not load resources. Please try again.'),
            'retry' => $t('Retry'),
            'locked' => $t('Locked'),
            'select' => $t('Select'),
            'close' => $t('Close'),
            // PB-TEMPLATE-REGISTRY-2-C — preview / materialization chrome. Availability
            // reasons and conflict copy are allowlisted here (never raw exceptions).
            'preview' => $t('Preview'),
            'back' => $t('Back'),
            'description' => $t('Description'),
            'insert' => $t('Insert'),
            'inserting' => $t('Inserting…'),
            'useTemplate' => $t('Use this template'),
            'unavailableReason' => $t('This resource is unavailable.'),
            'lockedReason' => $t('This resource requires an upgrade.'),
            'actionFailed' => $t('The action could not be completed. Please try again.'),
            'templateConflict' => $t('The draft changed since the preview. Please review again.'),
            'templateBlocked' => $t('This template cannot be applied here.'),
            'confirmReplaceTitle' => $t('Replace draft with this template?'),
            'confirmReplaceBody' => $t('This replaces your current draft layout. The published page will not change until you Publish. Unsaved draft changes will be replaced.'),
            'confirmReplace' => $t('Replace draft with this template'),
            'confirmCancel' => $t('Cancel / Keep current draft'),
            'applying' => $t('Applying…'),
        ];
    }
}
