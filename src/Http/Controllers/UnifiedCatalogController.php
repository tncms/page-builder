<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Plugins\PageBuilder\Catalog\UnifiedCatalog;

/**
 * PB-XREG-4 — read-only unified catalog (components, behaviors, data sources, design resources) with origin and
 * availability. Requires page-builder.edit. Filters are validated by the catalog; responses are never cached.
 */
final class UnifiedCatalogController
{
    public function __invoke(Request $request, UnifiedCatalog $catalog): JsonResponse
    {
        abort_unless(function_exists('cms_can') && cms_can('page-builder.edit'), 403);

        $filters = array_intersect_key($request->query(), array_flip(['type', 'origin', 'slug']));
        if (count($request->query()) !== count($filters)) {
            return $this->error();
        }

        try {
            $records = $catalog->records($filters);
        } catch (InvalidArgumentException) {
            return $this->error();
        }

        return response()->json([
            'success' => true,
            'data' => ['version' => UnifiedCatalog::VERSION, 'records' => $records],
        ])->header('Cache-Control', 'no-store, private');
    }

    private function error(): JsonResponse
    {
        return response()->json(['success' => false, 'error' => 'invalid_filter'], 422)
            ->header('Cache-Control', 'no-store, private');
    }
}
